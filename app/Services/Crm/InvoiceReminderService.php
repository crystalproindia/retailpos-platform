<?php

namespace App\Services\Crm;

use App\Enums\Crm\InvoiceReminderStage;
use App\Enums\Crm\InvoiceStatus;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoiceReminderRule;
use App\Models\Crm\CrmInvoiceReminderSetting;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\EmailDeliveryService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class InvoiceReminderService
{
    public function __construct(
        private readonly InvoiceReminderSettingsService $settings,
        private readonly PublicInvoiceService $links,
        private readonly EmailDeliveryService $email,
        private readonly AuditLogger $audit,
    ) {}

    /** @return array{eligible:bool,reason:string,rule:?CrmInvoiceReminderRule} */
    public function automaticEligibility(CrmInvoice $invoice, ?CarbonImmutable $now = null): array
    {
        $invoice->loadMissing(['company', 'creator']);
        $company = $invoice->company;
        $now ??= CarbonImmutable::now($company->timezone ?: config('app.timezone'));
        $today = $now->toDateString();
        $setting = $this->settings->find($company);

        if (! $company->is_active) return $this->ineligible('The company is inactive.');
        if (! $setting?->automatic_enabled) return $this->ineligible('Automatic reminders are disabled.');
        if (! $this->canRemind($invoice)) return $this->ineligible('The invoice is not eligible for reminders.');
        if (! filter_var($invoice->billing_email, FILTER_VALIDATE_EMAIL)) return $this->ineligible('The invoice recipient email is invalid or unavailable.');
        if ($invoice->do_not_remind_before?->isAfter($today)) return $this->ineligible('The invoice is paused until its reminder date.');
        if ($this->hasPermanentRecipientFailure($invoice)) return $this->ineligible('A permanent delivery failure was recorded for this invoice.');
        if ($this->hasSuccessfulFinalNotice($invoice)) return $this->ineligible('A final notice was already sent.');

        $rule = $setting->rules->first(fn (CrmInvoiceReminderRule $rule): bool => $rule->enabled && $invoice->due_date?->toDateString() === $now->subDays($rule->offset_days)->toDateString());
        if (! $rule) return $this->ineligible('No enabled reminder stage matches today.');
        if ($this->hasStageInProgressOrSent($invoice, $rule->stage, 'automatic')) return $this->ineligible('This reminder stage has already been queued or sent.');
        if ($this->withinCooldown($invoice, $setting->minimum_cooldown_hours, $now)) return $this->ineligible('A reminder was sent recently.');
        if (! $this->systemActor($invoice)) return $this->ineligible('No active company user is available to issue the secure invoice link.');

        return ['eligible' => true, 'reason' => 'Eligible', 'rule' => $rule];
    }

    /** @return array{delivery:NotificationDelivery,configured:bool,queued:bool} */
    public function queueAutomatic(CrmInvoice $invoice, ?CarbonImmutable $now = null): array
    {
        $decision = $this->automaticEligibility($invoice, $now);
        if (! $decision['eligible']) {
            throw ValidationException::withMessages(['invoice' => $decision['reason']]);
        }

        $actor = $this->systemActor($invoice);

        return $this->queue($invoice, $actor, $decision['rule'], 'automatic', null, null, $now);
    }

    public function canSendQueuedAutomatic(NotificationDelivery $delivery): bool
    {
        if ($delivery->reminder_source !== 'automatic' || $delivery->related_type !== (new CrmInvoice)->getMorphClass()) {
            return true;
        }

        $invoice = CrmInvoice::query()
            ->with('company')
            ->where('company_id', $delivery->company_id)
            ->find($delivery->related_id);
        if (! $invoice || ! $invoice->company->is_active || ! $this->canRemind($invoice) || ! filter_var($invoice->billing_email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        if (strcasecmp(trim((string) $invoice->billing_email), trim((string) $delivery->recipient)) !== 0) {
            return false;
        }

        return (bool) $this->settings->find($invoice->company)?->automatic_enabled;
    }

    /** @return array{delivery:NotificationDelivery,configured:bool,queued:bool} */
    public function queueManual(CrmInvoice $invoice, User $user, InvoiceReminderStage $stage, bool $attachPdf, ?string $note = null): array
    {
        $invoice->loadMissing('company');
        if (! $this->canRemind($invoice)) {
            throw ValidationException::withMessages(['invoice' => 'Only issued invoices with an outstanding balance can receive a payment reminder.']);
        }
        if (! filter_var($invoice->billing_email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'This invoice does not have a valid customer email address.']);
        }
        if ($this->hasPermanentRecipientFailure($invoice)) {
            throw ValidationException::withMessages(['email' => 'A permanent delivery failure was recorded for this invoice recipient. Update the customer email before sending another reminder.']);
        }

        $setting = $this->settings->ensure($invoice->company);
        if ($this->withinCooldown($invoice, $setting->minimum_cooldown_hours, CarbonImmutable::now($invoice->company->timezone ?: config('app.timezone')))) {
            throw ValidationException::withMessages(['invoice' => 'A payment reminder was already sent recently. Please wait before sending another one.']);
        }

        $rule = $setting->rules->first(fn (CrmInvoiceReminderRule $rule): bool => $rule->stage === $stage);
        if (! $rule) {
            throw ValidationException::withMessages(['stage' => 'The selected reminder type is unavailable.']);
        }

        return $this->queue($invoice, $user, $rule, 'manual', $note, $attachPdf, null);
    }

    /** @return array{delivery:NotificationDelivery,configured:bool,queued:bool} */
    private function queue(CrmInvoice $invoice, User $actor, CrmInvoiceReminderRule $rule, string $source, ?string $note, ?bool $attachPdf, ?CarbonImmutable $now): array
    {
        $invoice->loadMissing('company');
        $now ??= CarbonImmutable::now($invoice->company->timezone ?: config('app.timezone'));
        $stage = $rule->stage instanceof InvoiceReminderStage ? $rule->stage : InvoiceReminderStage::from((string) $rule->stage);
        $includeLink = $source === 'manual' || $rule->include_secure_link;
        $link = $includeLink ? $this->links->issue($invoice, $actor, false, $now->addDays(30)->endOfDay()) : null;
        $message = $this->replaceTokens($rule->intro_message, $invoice);
        if (filled($note)) $message .= "\n\n".trim($note);
        if ($attachPdf ?? $rule->attach_pdf) $message .= "\n\nA PDF copy of the invoice is attached for your records.";
        $manualKey = $source === 'manual' ? ':'.$now->toDateString() : '';

        $delivery = $this->email->queue(
            $invoice->company_id,
            (string) $invoice->billing_email,
            $this->replaceTokens($rule->subject, $invoice),
            'invoice_reminder_'.$stage->value,
            [
                'heading' => $stage->label().' payment reminder',
                'greeting' => 'Hello '.($invoice->billing_name ?: $invoice->billing_company ?: 'there').',',
                'message' => $message,
                'details' => [
                    'Business' => $invoice->company->trade_name ?: $invoice->company->legal_name ?: $invoice->company->name,
                    'Invoice' => $invoice->invoice_number,
                    'Invoice date' => $invoice->issue_date?->format('d M Y') ?: 'Not specified',
                    'Due date' => $invoice->due_date?->format('d M Y') ?: 'Not specified',
                    'Original total' => $this->amount($invoice->grand_total, $invoice->currency),
                    'Amount paid' => $this->amount($invoice->amount_paid, $invoice->currency),
                    'Outstanding balance' => $this->amount($invoice->balance_due, $invoice->currency),
                    'Reminder stage' => $stage->label(),
                ],
                'action_url' => $link?->url,
                'action_label' => $link ? 'View invoice securely' : null,
                'attachment_type' => ($attachPdf ?? $rule->attach_pdf) ? InvoiceEmailAttachmentService::TYPE : null,
            ],
            $invoice,
            $actor,
            'invoice-reminder:'.hash('sha256', implode('|', [$source, $invoice->id, $stage->value.$manualKey, strtolower((string) $invoice->billing_email)])),
            $invoice->billing_name ?: $invoice->billing_company,
            $stage->value,
            $source,
        );

        $this->audit->record('crm.invoice.reminder_queued', $invoice, 'Invoice payment reminder queued', [
            'company_id' => $invoice->company_id,
            'delivery_id' => $delivery->id,
            'stage' => $stage->value,
            'source' => $source,
        ]);

        return ['delivery' => $delivery, 'configured' => $delivery->status !== 'skipped_not_configured', 'queued' => $delivery->status === 'queued'];
    }

    private function canRemind(CrmInvoice $invoice): bool
    {
        return $invoice->balance_due > 0
            && $invoice->due_date !== null
            && in_array($invoice->status, [InvoiceStatus::Issued, InvoiceStatus::Sent, InvoiceStatus::Viewed, InvoiceStatus::PartiallyPaid, InvoiceStatus::Overdue], true);
    }

    private function hasStageInProgressOrSent(CrmInvoice $invoice, InvoiceReminderStage $stage, string $source): bool
    {
        return NotificationDelivery::query()
            ->where('company_id', $invoice->company_id)
            ->where('related_type', $invoice->getMorphClass())
            ->where('related_id', $invoice->id)
            ->where('reminder_stage', $stage->value)
            ->where('reminder_source', $source)
            ->whereIn('status', ['queued', 'processing', 'sent', 'delivered'])
            ->exists();
    }

    private function hasPermanentRecipientFailure(CrmInvoice $invoice): bool
    {
        return NotificationDelivery::query()
            ->where('company_id', $invoice->company_id)
            ->where('related_type', $invoice->getMorphClass())
            ->where('related_id', $invoice->id)
            ->whereNotNull('reminder_stage')
            ->whereIn('status', ['bounced', 'rejected', 'permanently_failed'])
            ->exists();
    }

    private function hasSuccessfulFinalNotice(CrmInvoice $invoice): bool
    {
        return NotificationDelivery::query()
            ->where('company_id', $invoice->company_id)
            ->where('related_type', $invoice->getMorphClass())
            ->where('related_id', $invoice->id)
            ->where('reminder_stage', InvoiceReminderStage::FinalNotice->value)
            ->where('reminder_source', 'automatic')
            ->whereIn('status', ['sent', 'delivered'])
            ->exists();
    }

    private function withinCooldown(CrmInvoice $invoice, int $hours, CarbonImmutable $now): bool
    {
        return NotificationDelivery::query()
            ->where('company_id', $invoice->company_id)
            ->where('related_type', $invoice->getMorphClass())
            ->where('related_id', $invoice->id)
            ->whereNotNull('reminder_stage')
            ->whereIn('status', ['queued', 'processing', 'sent', 'delivered', 'temporarily_failed'])
            ->where('created_at', '>=', $now->subHours($hours))
            ->exists();
    }

    private function systemActor(CrmInvoice $invoice): ?User
    {
        return $invoice->creator
            ?? User::query()->where('company_id', $invoice->company_id)->whereIn('role', ['administrator', 'manager'])->orderBy('id')->first();
    }

    private function replaceTokens(string $text, CrmInvoice $invoice): string
    {
        return strtr($text, [
            '{invoice_number}' => $invoice->invoice_number,
            '{due_date}' => $invoice->due_date?->format('d M Y') ?: 'Not specified',
            '{outstanding_balance}' => $this->amount($invoice->balance_due, $invoice->currency),
            '{business_name}' => $invoice->company->trade_name ?: $invoice->company->legal_name ?: $invoice->company->name,
        ]);
    }

    private function amount(string|int|float|null $value, string $currency): string
    {
        return $currency.' '.number_format((float) $value, 2);
    }

    /** @return array{eligible:false,reason:string,rule:null} */
    private function ineligible(string $reason): array
    {
        return ['eligible' => false, 'reason' => $reason, 'rule' => null];
    }
}

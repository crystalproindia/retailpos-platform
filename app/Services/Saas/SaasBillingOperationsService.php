<?php

namespace App\Services\Saas;

use App\Models\SaasBillingPayment;
use App\Models\SaasSubscription;
use App\Models\SaasSubscriptionInvoice;
use App\Services\Notifications\EmailDeliveryService;
use Carbon\CarbonImmutable;

class SaasBillingOperationsService
{
    public function __construct(
        private readonly SaasSubscriptionInvoiceService $invoices,
        private readonly SubscriptionService $subscriptions,
        private readonly EmailDeliveryService $emails,
    ) {}

    /** @return array{inspected:int,created:int,skipped:int} */
    public function generateInvoices(bool $dryRun, ?int $companyId = null, ?int $subscriptionId = null, ?string $date = null): array
    {
        $today = CarbonImmutable::parse($date ?? today());
        $summary = ['inspected' => 0, 'created' => 0, 'skipped' => 0];
        SaasSubscription::query()->with('company')->whereIn('status', ['active', 'grace_period', 'past_due'])
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->when($subscriptionId, fn ($query) => $query->whereKey($subscriptionId))->orderBy('id')->chunkById(100, function ($subscriptions) use (&$summary, $today, $dryRun): void {
                foreach ($subscriptions as $subscription) {
                    $summary['inspected']++;
                    if (! $subscription->renewal_date || $subscription->renewal_date->isAfter($today->addDays((int) config('saas.billing.invoice_lead_days', 7)))) { $summary['skipped']++; continue; }
                    $start = $subscription->current_period_ends_at?->toImmutable() ?? $today;
                    $end = $this->periodEnd($start, $subscription->billing_interval);
                    if ($dryRun) { $summary['created']++; continue; }
                    $invoice = $this->invoices->create($subscription, null, [
                        'invoice_type' => 'renewal', 'billing_period_starts_at' => $start, 'billing_period_ends_at' => $end,
                        'issue_date' => $today, 'due_date' => min($subscription->renewal_date->toImmutable(), $today->addDays(7)),
                        'idempotency_key' => 'scheduled-invoice:'.$subscription->id.':'.$start->toDateString(),
                    ]);
                    $this->invoices->issue($invoice, null, $invoice->due_date?->toImmutable());
                    $summary['created']++;
                }
            });
        return $summary;
    }

    /** @return array{inspected:int,overdue:int,transitions:int} */
    public function processOverdue(bool $dryRun): array
    {
        $summary = ['inspected' => 0, 'overdue' => 0, 'transitions' => 0];
        SaasSubscriptionInvoice::query()->with('subscription')->whereIn('status', ['issued', 'partially_paid'])->whereDate('due_date', '<', today())->orderBy('id')->chunkById(100, function ($invoices) use (&$summary, $dryRun): void {
            foreach ($invoices as $invoice) {
                $summary['inspected']++; $summary['overdue']++;
                if ($invoice->subscription && in_array($invoice->subscription->status, ['active', 'grace_period'], true)) $summary['transitions']++;
                if ($dryRun) continue;
                $invoice->update(['status' => 'overdue']);
                if ($invoice->subscription && in_array($invoice->subscription->status, ['active', 'grace_period'], true)) {
                    $this->subscriptions->transition($invoice->subscription, 'past_due', null, 'Subscription invoice is overdue.', 'billing-overdue:'.$invoice->id.':'.today()->toDateString());
                }
            }
        });
        return $summary;
    }

    /** @return array{inspected:int,reminders:int} */
    public function sendReminders(bool $dryRun): array
    {
        $summary = ['inspected' => 0, 'reminders' => 0];
        SaasSubscriptionInvoice::query()->with('company')->whereIn('status', ['issued', 'partially_paid', 'overdue'])->where('balance_due', '>', 0)->orderBy('id')->chunkById(100, function ($invoices) use (&$summary, $dryRun): void {
            foreach ($invoices as $invoice) {
                $summary['inspected']++;
                $days = $invoice->due_date ? (int) today()->diffInDays($invoice->due_date, false) : null;
                if (! in_array($days, [7, 0, -7], true) || blank($invoice->billing_email)) continue;
                $summary['reminders']++;
                if ($dryRun) continue;
                $this->emails->queue($invoice->company_id, $invoice->billing_email, 'Subscription invoice '.$invoice->invoice_number, 'saas_billing_reminder', [
                    'heading' => 'Subscription payment reminder', 'greeting' => $invoice->billing_name ?: 'Hello,',
                    'message' => 'Your subscription invoice has an outstanding balance.',
                    'details' => ['Invoice' => $invoice->invoice_number, 'Amount due' => $invoice->currency.' '.$invoice->balance_due, 'Due date' => $invoice->due_date?->format('d M Y')],
                    'action_url' => route('account.subscription.billing.index'), 'action_label' => 'View billing',
                ], $invoice, idempotencyKey: 'saas-billing-reminder:'.$invoice->id.':'.$days);
            }
        });
        return $summary;
    }

    /** @return array{inspected:int,matched:int,exceptions:int} */
    public function reconcile(bool $dryRun, ?int $companyId = null): array
    {
        $summary = ['inspected' => 0, 'matched' => 0, 'exceptions' => 0];
        SaasBillingPayment::query()->with('invoice')->when($companyId, fn ($query) => $query->where('company_id', $companyId))->whereIn('reconciliation_status', ['unreconciled', 'matched'])->orderBy('id')->chunkById(100, function ($payments) use (&$summary, $dryRun): void {
            foreach ($payments as $payment) {
                $summary['inspected']++;
                $matches = $payment->status === 'confirmed' && $payment->invoice && $payment->invoice->company_id === $payment->company_id && $payment->amount > 0;
                $matches ? $summary['matched']++ : $summary['exceptions']++;
                if (! $dryRun) $payment->update(['reconciliation_status' => $matches ? 'matched' : 'exception']);
            }
        });
        return $summary;
    }

    private function periodEnd(CarbonImmutable $start, string $interval): CarbonImmutable
    {
        return match ($interval) { 'quarterly' => $start->addMonths(3), 'half-yearly' => $start->addMonths(6), 'yearly' => $start->addYear(), default => $start->addMonth() };
    }
}

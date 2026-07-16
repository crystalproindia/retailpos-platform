<?php

namespace App\Services\Crm;

use App\Contracts\Events\DomainEvent;
use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Crm\QuotationStatus;
use App\Events\Domain\Crm\QuotationAccepted;
use App\Events\Domain\Crm\QuotationCreated;
use App\Events\Domain\Crm\QuotationRejected;
use App\Events\Domain\Crm\QuotationSent;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\CrmQuotation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(CrmLead $lead, User $user, array $data): CrmQuotation
    {
        return DB::transaction(function () use ($lead, $user, $data): CrmQuotation {
            $calculation = $this->calculate($data['items']);
            $quotation = CrmQuotation::create($this->quotationPayload($lead, $user, $data, $calculation) + [
                'quotation_number' => $this->nextQuotationNumber($lead->company_id),
                'status' => QuotationStatus::Draft,
                'created_by' => $user->id,
            ]);
            $this->replaceItems($quotation, $calculation['items']);

            $description = "Quotation {$quotation->quotation_number} created.";
            $this->recordActivity($lead, $user, $description);
            $this->auditLogger->record('crm.quotation.created', $quotation, $description, [
                'company_id' => $lead->company_id,
                'lead_id' => $lead->id,
            ]);
            $this->domainEvents->dispatch(new QuotationCreated(
                companyId: $lead->company_id,
                actorId: $user->id,
                aggregateType: CrmQuotation::class,
                aggregateId: $quotation->id,
                payload: $this->eventPayload($quotation, $lead),
            ));

            return $quotation->load(['lead', 'items', 'creator']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(CrmQuotation $quotation, User $user, array $data): CrmQuotation
    {
        $this->ensureEditable($quotation);

        return DB::transaction(function () use ($quotation, $user, $data): CrmQuotation {
            $calculation = $this->calculate($data['items']);
            $quotation->update($this->quotationPayload($quotation->lead()->firstOrFail(), $user, $data, $calculation) + [
                'updated_by' => $user->id,
            ]);
            $this->replaceItems($quotation, $calculation['items']);
            $this->recordActivity($quotation->lead()->firstOrFail(), $user, "Quotation {$quotation->quotation_number} updated.");
            $this->auditLogger->record('crm.quotation.updated', $quotation, 'Quotation updated', ['company_id' => $quotation->company_id, 'lead_id' => $quotation->lead_id]);

            return $quotation->refresh()->load(['lead', 'items', 'creator', 'updater']);
        });
    }

    public function markSent(CrmQuotation $quotation, User $user): CrmQuotation
    {
        return $this->transition($quotation, $user, QuotationStatus::Sent, [QuotationStatus::Draft]);
    }

    public function markAccepted(CrmQuotation $quotation, User $user): CrmQuotation
    {
        return $this->transition($quotation, $user, QuotationStatus::Accepted, [QuotationStatus::Sent]);
    }

    public function markRejected(CrmQuotation $quotation, User $user): CrmQuotation
    {
        return $this->transition($quotation, $user, QuotationStatus::Rejected, [QuotationStatus::Sent]);
    }

    public function markConverted(CrmQuotation $quotation, User $user): CrmQuotation
    {
        if ($quotation->status !== QuotationStatus::Accepted) {
            throw ValidationException::withMessages(['quotation' => 'Only accepted quotations can be prepared for customer conversion.']);
        }

        return DB::transaction(function () use ($quotation, $user): CrmQuotation {
            $quotation->update(['status' => QuotationStatus::Converted, 'converted_at' => now(), 'updated_by' => $user->id]);
            $this->recordActivity($quotation->lead()->firstOrFail(), $user, 'Quotation prepared for customer conversion.');
            $this->auditLogger->record('crm.quotation.converted', $quotation, 'Quotation prepared for customer conversion', ['company_id' => $quotation->company_id, 'lead_id' => $quotation->lead_id]);

            return $quotation->refresh()->load(['lead', 'items', 'creator', 'updater']);
        });
    }

    public function generatePublicLink(CrmQuotation $quotation, User $user): CrmQuotation
    {
        return DB::transaction(function () use ($quotation, $user): CrmQuotation {
            if (! $quotation->public_token) {
                do {
                    $token = Str::random(48);
                } while (CrmQuotation::query()->where('public_token', $token)->exists());

                $quotation->update([
                    'public_token' => $token,
                    'public_url' => route('quotations.public.show', $token),
                    'updated_by' => $user->id,
                ]);
                $this->recordActivity($quotation->lead()->firstOrFail(), $user, "Public link generated for quotation {$quotation->quotation_number}.");
                $this->auditLogger->record('crm.quotation.public_link_generated', $quotation, 'Quotation public link generated', ['company_id' => $quotation->company_id, 'lead_id' => $quotation->lead_id]);
            }

            return $quotation->refresh()->load(['lead', 'items', 'creator', 'updater']);
        });
    }

    /** @param array<int, array<string, mixed>> $items
     * @return array{subtotal: float, discount_total: float, tax_total: float, grand_total: float, items: array<int, array<string, mixed>>}
     */
    private function calculate(array $items): array
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $normalizedItems = [];

        foreach (array_values($items) as $index => $item) {
            $quantity = round((float) $item['quantity'], 3);
            $unitPrice = round((float) $item['unit_price'], 2);
            $gross = round($quantity * $unitPrice, 2);
            $discount = min(round((float) ($item['discount_amount'] ?? 0), 2), $gross);
            $taxRate = round((float) ($item['tax_rate'] ?? 0), 3);
            $taxAmount = round(($gross - $discount) * $taxRate / 100, 2);
            $lineTotal = round($gross - $discount + $taxAmount, 2);

            $subtotal += $gross;
            $discountTotal += $discount;
            $taxTotal += $taxAmount;
            $normalizedItems[] = [
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_amount' => $discount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
                'sort_order' => $index + 1,
            ];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'grand_total' => round($subtotal - $discountTotal + $taxTotal, 2),
            'items' => $normalizedItems,
        ];
    }

    /** @param array<string, mixed> $data
     * @param array{subtotal: float, discount_total: float, tax_total: float, grand_total: float, items: array<int, array<string, mixed>>} $calculation
     * @return array<string, mixed>
     */
    private function quotationPayload(CrmLead $lead, User $user, array $data, array $calculation): array
    {
        return Arr::only($data, ['title', 'customer_name', 'customer_company', 'customer_email', 'customer_phone', 'billing_address', 'currency', 'valid_until', 'notes', 'terms_conditions', 'internal_remarks']) + [
            'lead_id' => $lead->id,
            'company_id' => $lead->company_id,
            'subtotal' => $calculation['subtotal'],
            'discount_total' => $calculation['discount_total'],
            'tax_total' => $calculation['tax_total'],
            'grand_total' => $calculation['grand_total'],
            'updated_by' => $user->id,
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    private function replaceItems(CrmQuotation $quotation, array $items): void
    {
        $quotation->items()->delete();
        $quotation->items()->createMany($items);
    }

    private function transition(CrmQuotation $quotation, User $user, QuotationStatus $to, array $allowedFrom): CrmQuotation
    {
        if (! in_array($quotation->status, $allowedFrom, true)) {
            throw ValidationException::withMessages(['quotation' => "This quotation cannot be marked as {$to->label()}."]);
        }

        return DB::transaction(function () use ($quotation, $user, $to): CrmQuotation {
            $lead = $quotation->lead()->firstOrFail();
            $timestampColumn = match ($to) {
                QuotationStatus::Sent => 'sent_at',
                QuotationStatus::Accepted => 'accepted_at',
                QuotationStatus::Rejected => 'rejected_at',
                default => null,
            };
            $quotation->update(array_filter([
                'status' => $to,
                $timestampColumn => now(),
                'updated_by' => $user->id,
            ], fn ($value, $key) => $key !== null, ARRAY_FILTER_USE_BOTH));

            $activity = match ($to) {
                QuotationStatus::Sent => 'Quotation sent.',
                QuotationStatus::Accepted => 'Quotation accepted.',
                QuotationStatus::Rejected => 'Quotation rejected.',
                default => 'Quotation updated.',
            };
            $this->recordActivity($lead, $user, $activity);
            $this->updateLeadWorkflow($lead, $to);
            $this->auditLogger->record('crm.quotation.'.$to->value, $quotation, rtrim($activity, '.'), ['company_id' => $quotation->company_id, 'lead_id' => $lead->id]);
            $this->domainEvents->dispatch($this->eventFor($to, $quotation, $lead, $user));

            return $quotation->refresh()->load(['lead.status', 'lead.assignedUser', 'items', 'creator', 'updater']);
        });
    }

    private function updateLeadWorkflow(CrmLead $lead, QuotationStatus $status): void
    {
        $stage = match ($status) {
            QuotationStatus::Sent => LeadStageType::Proposal,
            QuotationStatus::Accepted => LeadStageType::Won,
            default => null,
        };

        if (! $stage) {
            return;
        }

        $leadStatus = CrmLeadStatus::query()
            ->where('company_id', $lead->company_id)
            ->where('stage_type', $stage->value)
            ->where('is_active', true)
            ->first();

        if (! $leadStatus) {
            return;
        }

        $payload = ['status_id' => $leadStatus->id];
        if ($status === QuotationStatus::Accepted) {
            $payload['won_at'] = now();
        }
        $lead->update($payload);
    }

    private function recordActivity(CrmLead $lead, User $user, string $subject): void
    {
        CrmActivity::create([
            'company_id' => $lead->company_id,
            'crm_lead_id' => $lead->id,
            'assigned_user_id' => $lead->assigned_user_id,
            'created_by' => $user->id,
            'type' => ActivityType::Note,
            'subject' => $subject,
            'description' => $subject,
            'scheduled_at' => now(),
            'completed_at' => now(),
            'priority' => $lead->priority ?? LeadPriority::Medium,
        ]);
    }

    private function nextQuotationNumber(int $companyId): string
    {
        $year = now()->format('Y');
        $prefix = "RPQ-{$year}-";
        $lastNumber = CrmQuotation::query()
            ->where('company_id', $companyId)
            ->where('quotation_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->latest('id')
            ->value('quotation_number');
        $lastSequence = $lastNumber ? (int) substr($lastNumber, -6) : 0;

        return $prefix.str_pad((string) ($lastSequence + 1), 6, '0', STR_PAD_LEFT);
    }

    private function eventFor(QuotationStatus $status, CrmQuotation $quotation, CrmLead $lead, User $user): DomainEvent
    {
        $event = match ($status) {
            QuotationStatus::Sent => QuotationSent::class,
            QuotationStatus::Accepted => QuotationAccepted::class,
            QuotationStatus::Rejected => QuotationRejected::class,
            default => throw new \LogicException('No quotation domain event exists for this status.'),
        };

        return new $event(
            companyId: $quotation->company_id,
            actorId: $user->id,
            aggregateType: CrmQuotation::class,
            aggregateId: $quotation->id,
            payload: $this->eventPayload($quotation, $lead),
        );
    }

    /** @return array<string, mixed> */
    private function eventPayload(CrmQuotation $quotation, CrmLead $lead): array
    {
        return [
            'quotation_id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'lead_id' => $lead->id,
            'lead_title' => $lead->title,
            'business_name' => $lead->business_name,
            'assigned_user_id' => $lead->assigned_user_id,
            'grand_total' => $quotation->grand_total,
            'currency' => $quotation->currency,
        ];
    }

    private function ensureEditable(CrmQuotation $quotation): void
    {
        if (! $quotation->status?->isEditable()) {
            throw ValidationException::withMessages(['quotation' => 'Only draft quotations can be edited.']);
        }
    }
}

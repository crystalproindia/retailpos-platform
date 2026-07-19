<?php

namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\QuotationStatus;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmQuotation;
use App\Notifications\PlatformNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicQuotationService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function find(string $token): CrmQuotation
    {
        $quotation = CrmQuotation::query()
            ->where('public_token_hash', hash('sha256', $token))
            ->whereNull('public_token_revoked_at')
            ->with(['company', 'lead.assignedUser', 'items'])
            ->firstOrFail();

        if ($quotation->public_token_expires_at?->isPast()) {
            abort(404);
        }

        return $quotation;
    }

    public function recordView(CrmQuotation $quotation): CrmQuotation
    {
        if ($quotation->status === QuotationStatus::Sent) {
            $quotation->status = QuotationStatus::Viewed;
        }
        $quotation->forceFill([
            'first_viewed_at' => $quotation->first_viewed_at ?? now(),
            'last_viewed_at' => now(),
            'public_view_count' => $quotation->public_view_count + 1,
        ])->save();
        $this->auditLogger->record('crm.quotation.viewed', $quotation, 'Quotation viewed through a public link', [
            'company_id' => $quotation->company_id,
            'view_count' => $quotation->public_view_count,
        ]);

        return $quotation->refresh()->load(['company', 'lead.assignedUser', 'items']);
    }

    /** @param array{name: string, message?: string, rejection_reason?: string} $data */
    public function respond(CrmQuotation $quotation, string $decision, array $data, string $ip): CrmQuotation
    {
        if (! in_array($decision, ['accepted', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'The quotation decision is invalid.']);
        }
        if ($quotation->public_responded_at !== null || in_array($quotation->status, [QuotationStatus::Accepted, QuotationStatus::Rejected], true)) {
            throw ValidationException::withMessages(['quotation' => 'This quotation already has a recorded customer decision.']);
        }
        if ($quotation->valid_until?->isPast()) {
            throw ValidationException::withMessages(['quotation' => 'This quotation has expired and cannot be accepted or rejected online.']);
        }

        return DB::transaction(function () use ($quotation, $decision, $data, $ip): CrmQuotation {
            $status = $decision === 'accepted' ? QuotationStatus::Accepted : QuotationStatus::Rejected;
            $quotation->update([
                'status' => $status,
                'accepted_at' => $status === QuotationStatus::Accepted ? now() : null,
                'rejected_at' => $status === QuotationStatus::Rejected ? now() : null,
                'public_responded_at' => now(),
                'public_response_name' => $data['name'],
                'public_response_message' => $data['message'] ?? null,
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ]);
            $lead = $quotation->lead;
            if ($lead) {
                CrmActivity::create([
                    'company_id' => $quotation->company_id,
                    'crm_lead_id' => $lead->id,
                    'opportunity_id' => $quotation->opportunity_id,
                    'assigned_user_id' => $lead->assigned_user_id,
                    'created_by' => null,
                    'type' => ActivityType::Note,
                    'subject' => 'Customer '.$decision.' quotation '.$quotation->quotation_number.'.',
                    'scheduled_at' => now(),
                    'completed_at' => now(),
                    'follow_up_status' => 'completed',
                    'priority' => $lead->priority ?? LeadPriority::Medium,
                ]);
            }
            $this->auditLogger->record('crm.quotation.'.$decision.'_public', $quotation, 'Customer '.$decision.' quotation through public link', [
                'company_id' => $quotation->company_id,
                'ip_hash' => hash('sha256', $ip.config('app.key')),
            ]);
            $lead?->assignedUser?->notify(new PlatformNotification(
                channel: 'database',
                eventKey: 'crm.quotation.'.$decision,
                title: 'Quotation '.$decision,
                message: 'Customer '.$decision.' quotation '.$quotation->quotation_number.'.',
                actionUrl: route('crm.quotations.show', $quotation),
                severity: $decision === 'accepted' ? 'success' : 'warning',
                aggregateType: CrmQuotation::class,
                aggregateId: $quotation->id,
            ));

            return $quotation->refresh()->load(['company', 'lead.assignedUser', 'items']);
        });
    }
}

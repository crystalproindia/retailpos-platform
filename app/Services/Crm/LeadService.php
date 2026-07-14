<?php

namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\UserRole;
use App\Events\Domain\Crm\LeadAssigned;
use App\Events\Domain\Crm\LeadCreated;
use App\Events\Domain\Crm\LeadStatusChanged;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class LeadService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): CrmLead
    {
        $payload = $this->payload($data);

        $lead = CrmLead::create($payload + $this->lifecycleTimestamps((int) $data['status_id']) + [
            'company_id' => $user->company_id,
            'branch_id' => $data['branch_id'] ?? $user->branch_id,
            'created_by' => $user->id,
            'assigned_user_id' => $data['assigned_user_id'] ?? $user->id,
        ]);

        $this->syncTags($lead, $data['tag_ids'] ?? []);
        $this->recordCreatedActivity($lead, $user);
        $this->auditLogger->record('crm.lead.created', $lead, 'CRM lead created');
        $this->domainEvents->dispatch(new LeadCreated(
            companyId: $lead->company_id,
            actorId: $user->id,
            aggregateType: CrmLead::class,
            aggregateId: $lead->id,
            payload: $this->eventPayload($lead),
        ));

        return $lead->load(['source', 'status', 'assignedUser', 'tags']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CrmLead $lead, User $user, array $data): CrmLead
    {
        $originalStatusId = $lead->status_id;
        $originalAssigneeId = $lead->assigned_user_id;
        $originalPriority = $lead->priority?->value ?? $lead->priority;
        $originalFollowUpAt = $lead->next_follow_up_at?->toDateTimeString();
        $payload = $this->payload($data);

        if (array_key_exists('status_id', $payload) && (int) $payload['status_id'] !== (int) $originalStatusId) {
            $payload += $this->lifecycleTimestamps((int) $payload['status_id'], $lead);
        }

        $lead->update($payload);
        $this->syncTags($lead, $data['tag_ids'] ?? null);
        $this->auditLogger->record('crm.lead.updated', $lead, 'CRM lead updated', [
            'updated_by' => $user->id,
        ]);

        if (isset($payload['status_id']) && (int) $payload['status_id'] !== (int) $originalStatusId) {
            $this->auditLogger->record('crm.lead.status_changed', $lead, 'CRM lead status changed', [
                'from_status_id' => $originalStatusId,
                'to_status_id' => $payload['status_id'],
                'changed_by' => $user->id,
            ]);
            $this->domainEvents->dispatch(new LeadStatusChanged(
                companyId: $lead->company_id,
                actorId: $user->id,
                aggregateType: CrmLead::class,
                aggregateId: $lead->id,
                payload: $this->eventPayload($lead, [
                    'from_status_id' => $originalStatusId,
                    'to_status_id' => $payload['status_id'],
                ]),
            ));
        }

        if (array_key_exists('assigned_user_id', $payload) && (int) $payload['assigned_user_id'] !== (int) $originalAssigneeId) {
            $this->auditLogger->record('crm.lead.assigned', $lead, 'CRM lead assigned', [
                'assigned_user_id' => $payload['assigned_user_id'],
                'assigned_by' => $user->id,
            ]);
            $this->domainEvents->dispatch(new LeadAssigned(
                companyId: $lead->company_id,
                actorId: $user->id,
                aggregateType: CrmLead::class,
                aggregateId: $lead->id,
                payload: $this->eventPayload($lead, ['assigned_by' => $user->id]),
            ));
        }

        if (array_key_exists('priority', $payload) && $payload['priority'] !== $originalPriority) {
            $this->auditLogger->record('crm.lead.priority_changed', $lead, 'CRM lead priority changed', [
                'from_priority' => $originalPriority,
                'to_priority' => $payload['priority'],
                'changed_by' => $user->id,
            ]);
        }

        if (array_key_exists('next_follow_up_at', $payload) && $this->dateTimeString($payload['next_follow_up_at']) !== $originalFollowUpAt) {
            $this->auditLogger->record('crm.lead.follow_up_updated', $lead, 'CRM lead follow-up updated', [
                'follow_up_at' => $payload['next_follow_up_at'],
                'updated_by' => $user->id,
            ]);
        }

        return $lead->refresh()->load(['source', 'status', 'assignedUser', 'tags']);
    }

    public function delete(CrmLead $lead): void
    {
        $lead->delete();
        $this->auditLogger->record('crm.lead.deleted', $lead, 'CRM lead deleted');
    }

    public function restore(CrmLead $lead): CrmLead
    {
        $lead->restore();
        $this->auditLogger->record('crm.lead.restored', $lead, 'CRM lead restored');

        return $lead;
    }

    public function updateStatus(CrmLead $lead, int $statusId, User $user): CrmLead
    {
        $oldStatusId = $lead->status_id;
        $lead->update(['status_id' => $statusId] + $this->lifecycleTimestamps($statusId, $lead));

        $this->auditLogger->record('crm.lead.status_changed', $lead, 'CRM lead status changed', [
            'from_status_id' => $oldStatusId,
            'to_status_id' => $statusId,
            'changed_by' => $user->id,
        ]);
        $this->domainEvents->dispatch(new LeadStatusChanged(
            companyId: $lead->company_id,
            actorId: $user->id,
            aggregateType: CrmLead::class,
            aggregateId: $lead->id,
            payload: $this->eventPayload($lead, [
                'from_status_id' => $oldStatusId,
                'to_status_id' => $statusId,
            ]),
        ));

        return $lead->refresh()->load('status');
    }

    public function assign(CrmLead $lead, int $assignedUserId, User $user): CrmLead
    {
        $lead->update(['assigned_user_id' => $assignedUserId]);

        $this->auditLogger->record('crm.lead.assigned', $lead, 'CRM lead assigned', [
            'assigned_user_id' => $assignedUserId,
            'assigned_by' => $user->id,
        ]);
        $this->domainEvents->dispatch(new LeadAssigned(
            companyId: $lead->company_id,
            actorId: $user->id,
            aggregateType: CrmLead::class,
            aggregateId: $lead->id,
            payload: $this->eventPayload($lead, [
                'assigned_user_id' => $assignedUserId,
                'assigned_by' => $user->id,
            ]),
        ));

        return $lead->refresh()->load('assignedUser');
    }

    /**
     * @param  array<int, int|string>  $leadIds
     */
    public function bulkStatus(User $user, array $leadIds, int $statusId): int
    {
        $count = $this->bulkQuery($user, $leadIds)
            ->update(['status_id' => $statusId, 'updated_at' => now()]);

        $this->auditLogger->record('crm.lead.bulk_status_changed', null, 'CRM lead bulk status changed', [
            'company_id' => $user->company_id,
            'lead_ids' => array_values($leadIds),
            'status_id' => $statusId,
            'changed_by' => $user->id,
        ]);

        return $count;
    }

    /**
     * @param  array<int, int|string>  $leadIds
     */
    public function bulkAssign(User $user, array $leadIds, int $assignedUserId): int
    {
        $count = $this->bulkQuery($user, $leadIds)
            ->update(['assigned_user_id' => $assignedUserId, 'updated_at' => now()]);

        $this->auditLogger->record('crm.lead.bulk_assigned', null, 'CRM lead bulk assignment completed', [
            'company_id' => $user->company_id,
            'lead_ids' => array_values($leadIds),
            'assigned_user_id' => $assignedUserId,
            'assigned_by' => $user->id,
        ]);

        return $count;
    }

    public function addNote(CrmLead $lead, User $user, string $body): void
    {
        $lead->notes()->create([
            'company_id' => $lead->company_id,
            'user_id' => $user->id,
            'body' => $body,
        ]);

        $this->auditLogger->record('crm.lead.note_added', $lead, 'CRM lead note added');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return Arr::only($data, [
            'branch_id',
            'crm_company_id',
            'crm_contact_id',
            'source_id',
            'status_id',
            'assigned_user_id',
            'title',
            'business_name',
            'contact_name',
            'email',
            'phone',
            'alternate_phone',
            'industry',
            'city',
            'country',
            'business_type',
            'interested_modules',
            'expected_value',
            'expected_timeline',
            'currency',
            'priority',
            'lead_score',
            'next_follow_up_at',
            'last_contacted_at',
            'lost_reason',
            'description',
            'metadata',
        ]);
    }

    /**
     * @param  array<int, int|string>|null  $tagIds
     */
    private function syncTags(CrmLead $lead, ?array $tagIds): void
    {
        if ($tagIds === null) {
            return;
        }

        $lead->tags()->sync($tagIds);
    }

    /**
     * @param  array<int, int|string>  $leadIds
     */
    private function bulkQuery(User $user, array $leadIds): Builder
    {
        return CrmLead::query()
            ->where('company_id', $user->company_id)
            ->whereIn('id', $leadIds)
            ->when($this->isSales($user), function (Builder $query) use ($user): void {
                $query->where(function (Builder $query) use ($user): void {
                    $query->where('assigned_user_id', $user->id)
                        ->orWhere('created_by', $user->id);
                });
            });
    }

    private function isSales(User $user): bool
    {
        $role = $user->role instanceof UserRole ? $user->role : UserRole::tryFrom((string) $user->role);

        return $role === UserRole::Sales;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function eventPayload(CrmLead $lead, array $overrides = []): array
    {
        return array_merge([
            'lead_id' => $lead->id,
            'lead_title' => $lead->title,
            'business_name' => $lead->business_name,
            'contact_name' => $lead->contact_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'assigned_user_id' => $lead->assigned_user_id,
            'status_id' => $lead->status_id,
            'expected_value' => $lead->expected_value,
            'priority' => $lead->priority?->value ?? $lead->priority,
        ], $overrides);
    }

    private function recordCreatedActivity(CrmLead $lead, User $user): void
    {
        CrmActivity::create([
            'company_id' => $lead->company_id,
            'crm_lead_id' => $lead->id,
            'assigned_user_id' => $lead->assigned_user_id,
            'created_by' => $user->id,
            'type' => ActivityType::Note,
            'subject' => 'Lead created',
            'description' => 'Lead captured'.($lead->source?->name ? ' from '.$lead->source->name.'.' : '.'),
            'scheduled_at' => now(),
            'completed_at' => now(),
            'priority' => $lead->priority,
        ]);
    }

    /**
     * @return array<string, Carbon>
     */
    private function lifecycleTimestamps(int $statusId, ?CrmLead $lead = null): array
    {
        $status = CrmLeadStatus::query()->find($statusId);

        if ($status?->is_won && ! $lead?->won_at) {
            return ['won_at' => now()];
        }

        if ($status?->is_lost && ! $lead?->lost_at) {
            return ['lost_at' => now()];
        }

        return [];
    }

    private function dateTimeString(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateTimeString() : null;
    }
}

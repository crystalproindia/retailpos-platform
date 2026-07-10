<?php

namespace App\Services\Crm;

use App\Enums\UserRole;
use App\Models\Crm\CrmLead;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class LeadService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): CrmLead
    {
        $lead = CrmLead::create($this->payload($data) + [
            'company_id' => $user->company_id,
            'branch_id' => $data['branch_id'] ?? $user->branch_id,
            'created_by' => $user->id,
            'assigned_user_id' => $data['assigned_user_id'] ?? $user->id,
        ]);

        $this->syncTags($lead, $data['tag_ids'] ?? []);
        $this->auditLogger->record('crm.lead.created', $lead, 'CRM lead created');

        return $lead->load(['source', 'status', 'assignedUser', 'tags']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CrmLead $lead, User $user, array $data): CrmLead
    {
        $lead->update($this->payload($data));
        $this->syncTags($lead, $data['tag_ids'] ?? null);
        $this->auditLogger->record('crm.lead.updated', $lead, 'CRM lead updated', [
            'updated_by' => $user->id,
        ]);

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
        $lead->update(['status_id' => $statusId]);

        $this->auditLogger->record('crm.lead.status_changed', $lead, 'CRM lead status changed', [
            'from_status_id' => $oldStatusId,
            'to_status_id' => $statusId,
            'changed_by' => $user->id,
        ]);

        return $lead->refresh()->load('status');
    }

    public function assign(CrmLead $lead, int $assignedUserId, User $user): CrmLead
    {
        $lead->update(['assigned_user_id' => $assignedUserId]);

        $this->auditLogger->record('crm.lead.assigned', $lead, 'CRM lead assigned', [
            'assigned_user_id' => $assignedUserId,
            'assigned_by' => $user->id,
        ]);

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
            'interested_modules',
            'expected_value',
            'currency',
            'priority',
            'lead_score',
            'next_follow_up_at',
            'last_contacted_at',
            'lost_reason',
            'description',
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
}

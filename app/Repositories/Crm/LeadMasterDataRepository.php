<?php

namespace App\Repositories\Crm;

use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\User;
use Illuminate\Support\Collection;

class LeadMasterDataRepository
{
    /** @return Collection<int, CrmLeadStatus> */
    public function statusesFor(User $user): Collection
    {
        return CrmLeadStatus::query()
            ->where('company_id', $user->company_id)
            ->withCount('leads')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return Collection<int, CrmLeadSource> */
    public function sourcesFor(User $user): Collection
    {
        return CrmLeadSource::query()
            ->where('company_id', $user->company_id)
            ->withCount('leads')
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function statusFor(User $user, int $statusId): CrmLeadStatus
    {
        return CrmLeadStatus::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($statusId);
    }

    public function sourceFor(User $user, int $sourceId): CrmLeadSource
    {
        return CrmLeadSource::query()
            ->where('company_id', $user->company_id)
            ->findOrFail($sourceId);
    }
}

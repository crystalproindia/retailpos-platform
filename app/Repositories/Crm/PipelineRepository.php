<?php

namespace App\Repositories\Crm;

use App\Models\Crm\CrmLeadStatus;
use App\Models\User;
use Illuminate\Support\Collection;

class PipelineRepository
{
    public function __construct(private readonly LeadRepository $leadRepository) {}

    /**
     * @return Collection<int, array{status: CrmLeadStatus, leads: Collection<int, mixed>, value: float|int|string|null}>
     */
    public function groupedForUser(User $user): Collection
    {
        $leadQuery = $this->leadRepository->queryForUser($user);

        return $this->leadRepository->statusesForCompany($user->company_id)
            ->map(fn (CrmLeadStatus $status): array => [
                'status' => $status,
                'leads' => (clone $leadQuery)->where('status_id', $status->id)->oldest('updated_at')->get(),
                'value' => (clone $leadQuery)->where('status_id', $status->id)->sum('expected_value'),
            ]);
    }
}

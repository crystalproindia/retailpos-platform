<?php

namespace App\Repositories\Promotions;

use App\Models\Promotions\PromotionSimulation;
use Illuminate\Support\Collection;

class PromotionSimulationRepository
{
    /** @return Collection<int, PromotionSimulation> */
    public function recentForCompany(int $companyId): Collection
    {
        return PromotionSimulation::query()->with('user')->where('company_id', $companyId)->latest('simulated_at')->limit(12)->get();
    }
}

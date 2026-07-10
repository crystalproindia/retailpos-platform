<?php

namespace App\Repositories;

use App\Models\DashboardStatistic;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardRepository
{
    /**
     * @return Collection<int, DashboardStatistic>
     */
    public function metricsFor(User $user): Collection
    {
        return DashboardStatistic::query()
            ->where(function ($query) use ($user): void {
                $query->where('company_id', $user->company_id)
                    ->orWhereNull('company_id');
            })
            ->orderBy('sort_order')
            ->get();
    }
}

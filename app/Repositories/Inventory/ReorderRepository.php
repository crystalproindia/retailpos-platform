<?php

namespace App\Repositories\Inventory;

use App\Models\Inventory\ReorderSuggestion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReorderRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ReorderSuggestion>
     */
    public function suggestions(int $companyId, array $filters = []): LengthAwarePaginator
    {
        return ReorderSuggestion::query()
            ->with(['product', 'warehouse'])
            ->where('company_id', $companyId)
            ->when(($filters['status'] ?? null), fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['risk'] ?? null), fn ($query, $risk) => $query->where('stockout_risk_level', $risk))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }
}

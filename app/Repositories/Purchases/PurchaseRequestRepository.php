<?php

namespace App\Repositories\Purchases;

use App\Models\Purchases\PurchaseRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PurchaseRequestRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PurchaseRequest>
     */
    public function paginateForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        return PurchaseRequest::query()
            ->with(['warehouse', 'requester', 'items.product', 'items.supplier'])
            ->where('company_id', $companyId)
            ->when(($filters['search'] ?? null), fn ($query, $search) => $query->where('request_number', 'like', "%{$search}%"))
            ->when(($filters['status'] ?? null), fn ($query, $status) => $query->where('status', $status))
            ->when(($filters['priority'] ?? null), fn ($query, $priority) => $query->where('priority', $priority))
            ->when(($filters['source_type'] ?? null), fn ($query, $source) => $query->where('source_type', $source))
            ->latest()
            ->paginate(15)
            ->withQueryString();
    }

    public function findForCompany(int $companyId, int $requestId): PurchaseRequest
    {
        return PurchaseRequest::query()
            ->with(['warehouse', 'requester', 'reviewer', 'items.product', 'items.supplier'])
            ->where('company_id', $companyId)
            ->findOrFail($requestId);
    }
}

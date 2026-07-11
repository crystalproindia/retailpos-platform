<?php

namespace App\Repositories\Inventory;

use App\Models\Inventory\SalesChannel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ChannelRepository
{
    /**
     * @return LengthAwarePaginator<int, SalesChannel>
     */
    public function paginate(int $companyId): LengthAwarePaginator
    {
        return SalesChannel::query()
            ->withCount('mappings')
            ->where('company_id', $companyId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
    }
}

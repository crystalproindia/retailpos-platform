<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsMenu;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CmsMenuRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        return CmsMenu::query()
            ->with('items')
            ->where('company_id', $companyId)
            ->when($filters['location'] ?? null, fn ($query, string $location) => $query->where('location', $location))
            ->when(($filters['trashed'] ?? null) === 'with', fn ($query) => $query->withTrashed())
            ->orderBy('location')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @return Collection<int, CmsMenu>
     */
    public function enabledMenus(int $companyId): Collection
    {
        return CmsMenu::query()
            ->with('items.children')
            ->where('company_id', $companyId)
            ->where('is_enabled', true)
            ->orderBy('location')
            ->get();
    }

    public function findForCompany(int $companyId, int $menuId, bool $withTrashed = false): CmsMenu
    {
        return CmsMenu::query()
            ->with('items')
            ->where('company_id', $companyId)
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->findOrFail($menuId);
    }
}

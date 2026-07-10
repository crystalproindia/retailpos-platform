<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsMedia;
use App\Models\Cms\CmsMediaFolder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CmsMediaRepository
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        return CmsMedia::query()
            ->with('folder')
            ->where('company_id', $companyId)
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('file_name', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($filters['folder_id'] ?? null, fn ($query, string $folderId) => $query->where('folder_id', $folderId))
            ->when(($filters['trashed'] ?? null) === 'with', fn ($query) => $query->withTrashed())
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * @return Collection<int, CmsMediaFolder>
     */
    public function foldersForCompany(int $companyId): Collection
    {
        return CmsMediaFolder::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }
}

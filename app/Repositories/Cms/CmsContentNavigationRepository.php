<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsFooterBlock;
use App\Models\Cms\CmsNavigationItem;
use Illuminate\Support\Collection;

class CmsContentNavigationRepository
{
    /** @return Collection<int, CmsNavigationItem> */
    public function navigation(int $companyId): Collection
    {
        return CmsNavigationItem::query()->where('company_id', $companyId)->with('parent')->orderBy('location')->orderBy('sort_order')->get();
    }

    public function navigationItem(int $companyId, int $id): CmsNavigationItem
    {
        return CmsNavigationItem::query()->where('company_id', $companyId)->findOrFail($id);
    }

    /** @return Collection<int, CmsFooterBlock> */
    public function footerBlocks(int $companyId): Collection
    {
        return CmsFooterBlock::query()->where('company_id', $companyId)->orderBy('sort_order')->get();
    }

    public function footerBlock(int $companyId, int $id): CmsFooterBlock
    {
        return CmsFooterBlock::query()->where('company_id', $companyId)->findOrFail($id);
    }
}

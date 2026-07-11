<?php

namespace App\Repositories\Inventory;

use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryTaxRate;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\ProductAttribute;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Collection;

class InventoryLookupRepository
{
    /**
     * @return array<string, Collection>
     */
    public function formOptions(int $companyId): array
    {
        return [
            'categories' => InventoryCategory::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'brands' => InventoryBrand::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'units' => InventoryUnit::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $companyId))->orderBy('name')->get(),
            'taxRates' => InventoryTaxRate::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $companyId))->orderBy('rate')->get(),
            'attributes' => ProductAttribute::query()->with('values')->where('company_id', $companyId)->orderBy('sort_order')->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->with('locations')->where('company_id', $companyId)->orderBy('name')->get(),
            'locations' => StockLocation::query()->with('warehouse')->where('company_id', $companyId)->orderBy('code')->get(),
        ];
    }

    public function categories(int $companyId): Collection
    {
        return InventoryCategory::query()->where('company_id', $companyId)->orderBy('name')->get();
    }

    public function brands(int $companyId): Collection
    {
        return InventoryBrand::query()->where('company_id', $companyId)->orderBy('name')->get();
    }
}

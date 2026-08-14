<?php

namespace App\Services\Purchases;

use App\Models\User;
use App\Services\Inventory\InventoryLocationAccessService;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Database\Eloquent\Builder;

class PurchaseAccessService
{
    public function __construct(
        private readonly InventoryLocationAccessService $locations,
        private readonly OutletAccessService $outlets,
    ) {}

    /** @template TModel of \Illuminate\Database\Eloquent\Model
     * @param Builder<TModel> $query
     * @return Builder<TModel>
     */
    public function scope(Builder $query, User $user): Builder
    {
        $query->where('company_id', $user->company_id);
        if ($user->isAdministrator()) {
            return $query;
        }

        $warehouseIds = $this->locations->accessibleWarehouses($user, false)->pluck('id');
        $outletIds = $this->outlets->accessibleOutlets($user, false)->pluck('id');

        return $query->where(function (Builder $records) use ($warehouseIds, $outletIds): void {
            if ($warehouseIds->isNotEmpty()) {
                $records->whereIn('warehouse_id', $warehouseIds);
            } else {
                $records->whereRaw('1 = 0');
            }

            if ($outletIds->isNotEmpty()) {
                $records->orWhere(function (Builder $unassignedWarehouse) use ($outletIds): void {
                    $unassignedWarehouse->whereNull('warehouse_id')->whereIn('branch_id', $outletIds);
                });
            }
        });
    }
}

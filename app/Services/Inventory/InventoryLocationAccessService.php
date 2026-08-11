<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventorySerialNumber;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Models\WorkforceEmployeeWarehouseAssignment;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class InventoryLocationAccessService
{
    public function __construct(private readonly OutletAccessService $outlets) {}

    /** @return Collection<int, Warehouse> */
    public function accessibleWarehouses(User $user, bool $activeOnly = true): Collection
    {
        return $this->query($user, $activeOnly)
            ->with('branch')
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function authorize(User $user, int|Warehouse $warehouse, bool $activeOnly = true): Warehouse
    {
        $id = $warehouse instanceof Warehouse ? $warehouse->id : $warehouse;
        $authorized = $this->query($user, $activeOnly)->find($id);

        if (! $authorized) {
            throw ValidationException::withMessages(['warehouse_id' => 'That stock location is not available to you.']);
        }

        return $authorized;
    }

    public function canAccess(User $user, Warehouse $warehouse): bool
    {
        return $this->query($user)->whereKey($warehouse->id)->exists();
    }

    /** @return Builder<InventorySerialNumber> */
    public function scopeSerials(Builder $query, User $user): Builder
    {
        $warehouseIds = $this->query($user, false)->pluck('id');
        $transferIds = StockTransfer::query()
            ->select('id')
            ->where('company_id', $user->company_id)
            ->where(fn (Builder $transfer) => $transfer
                ->whereIn('source_warehouse_id', $warehouseIds)
                ->orWhereIn('destination_warehouse_id', $warehouseIds));

        return $query
            ->where('company_id', $user->company_id)
            ->where(fn (Builder $serials) => $serials
                ->whereIn('warehouse_id', $warehouseIds)
                ->orWhere(fn (Builder $transit) => $transit
                    ->whereNull('warehouse_id')
                    ->where('reference_type', StockTransfer::class)
                    ->whereIn('reference_id', $transferIds)));
    }

    public function authorizeSerial(User $user, InventorySerialNumber $serial): void
    {
        if ($serial->company_id !== $user->company_id) {
            throw ValidationException::withMessages(['serial' => 'That serial number is not available to your company.']);
        }
        if ($serial->warehouse_id) {
            $this->authorize($user, $serial->warehouse_id, false);

            return;
        }
        if (! $this->scopeSerials(InventorySerialNumber::query()->whereKey($serial->id), $user)->exists()) {
            throw ValidationException::withMessages(['serial' => 'That serial number is not available to your stock locations.']);
        }
    }

    /** @return Builder<Warehouse> */
    public function query(User $user, bool $activeOnly = true): Builder
    {
        $query = Warehouse::query()->where('company_id', $user->company_id);
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($user->isAdministrator()) {
            return $query;
        }

        $outletIds = $this->outlets->accessibleOutlets($user, $activeOnly)->pluck('id');
        $warehouseIds = $user->workforce_employee_id
            ? WorkforceEmployeeWarehouseAssignment::query()
                ->where('company_id', $user->company_id)
                ->where('employee_id', $user->workforce_employee_id)
                ->where('is_active', true)
                ->pluck('warehouse_id')
            : collect();

        return $query->where(function (Builder $builder) use ($outletIds, $warehouseIds): void {
            if ($outletIds->isNotEmpty()) {
                $builder->whereIn('branch_id', $outletIds);
            } else {
                $builder->whereRaw('1 = 0');
            }

            if ($warehouseIds->isNotEmpty()) {
                $builder->orWhereIn('id', $warehouseIds);
            }
        });
    }
}

<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventorySerialNumber;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLocation;
use App\Services\Inventory\InventoryLocationAccessService;
use App\Services\Inventory\InventoryTraceabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryTraceabilityController extends Controller
{
    public function index(Request $request, InventoryLocationAccessService $access): View
    {
        $warehouses = $access->accessibleWarehouses($request->user());
        $warehouseIds = $warehouses->pluck('id');
        $batches = InventoryBatch::query()->with(['product', 'warehouse', 'location'])->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $warehouseIds)->when($request->filled('expiry'), function ($query) use ($request): void {
            $days = match ($request->string('expiry')->toString()) {
                'expired' => -1, '7' => 7, '30' => 30, '60' => 60, '90' => 90, default => null
            };
            if ($days === -1) {
                $query->whereDate('expires_at', '<', today());
            } elseif ($days) {
                $query->whereBetween('expires_at', [today(), today()->addDays($days)]);
            }
        })->when($request->filled('warehouse'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse')))->latest()->paginate(20, ['*'], 'batches')->withQueryString();
        $serials = $access->scopeSerials(InventorySerialNumber::query()->with(['product', 'warehouse', 'location']), $request->user())->when($request->filled('serial'), fn ($query) => $query->where('serial_number', 'like', '%'.$request->string('serial')->toString().'%'))->latest()->paginate(20, ['*'], 'serials')->withQueryString();

        return view('command-center.inventory.traceability.index', [
            'warehouses' => $warehouses,
            'locations' => StockLocation::query()->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $warehouseIds)->where('is_active', true)->get(),
            'products' => Product::query()->where('company_id', $request->user()->company_id)->where(fn ($query) => $query->where('track_batches', true)->orWhere('track_serials', true))->where('is_active', true)->orderBy('name')->get(),
            'batches' => $batches,
            'serials' => $serials,
            'expiry' => [
                'expired' => InventoryBatch::query()->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $warehouseIds)->whereDate('expires_at', '<', today())->sum('quantity_available'),
                'seven' => InventoryBatch::query()->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $warehouseIds)->whereBetween('expires_at', [today(), today()->addDays(7)])->sum('quantity_available'),
                'thirty' => InventoryBatch::query()->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $warehouseIds)->whereBetween('expires_at', [today(), today()->addDays(30)])->sum('quantity_available'),
                'ninety' => InventoryBatch::query()->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $warehouseIds)->whereBetween('expires_at', [today(), today()->addDays(90)])->sum('quantity_available'),
            ],
        ]);
    }

    public function storeBatch(Request $request, InventoryTraceabilityService $service): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer'], 'warehouse_id' => ['required', 'integer'], 'stock_location_id' => ['nullable', 'integer'], 'batch_number' => ['required', 'string', 'max:120'], 'manufactured_at' => ['nullable', 'date'], 'expires_at' => ['nullable', 'date', 'after_or_equal:manufactured_at'], 'quantity_on_hand' => ['required', 'numeric', 'min:0'], 'quantity_available' => ['nullable', 'numeric', 'min:0'], 'unit_cost' => ['nullable', 'numeric', 'min:0'], 'supplier_reference' => ['nullable', 'string', 'max:120'], 'receipt_reference' => ['nullable', 'string', 'max:120']]);
        $service->saveBatch($request->user(), $data);

        return back()->with('status', 'Batch saved. No stock balance was changed.');
    }

    public function storeSerials(Request $request, InventoryTraceabilityService $service): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer'], 'warehouse_id' => ['required', 'integer'], 'stock_location_id' => ['nullable', 'integer'], 'inventory_batch_id' => ['nullable', 'integer'], 'serial_numbers' => ['required', 'string', 'max:50000']]);
        $count = $service->createSerials($request->user(), $data);

        return back()->with('status', $count.' serial numbers added.');
    }

    public function updateSerial(Request $request, InventoryTraceabilityService $service, int $serial): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['available', 'reserved', 'sold', 'returned', 'damaged', 'in_transit'])], 'warehouse_id' => ['nullable', 'integer'], 'stock_location_id' => ['nullable', 'integer']]);
        $record = InventorySerialNumber::query()->where('company_id', $request->user()->company_id)->findOrFail($serial);
        $service->updateSerial($request->user(), $record, $data);

        return back()->with('status', 'Serial status updated.');
    }
}

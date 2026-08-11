<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Models\Inventory\StockAdjustment;
use App\Repositories\Inventory\InventoryLookupRepository;
use App\Repositories\Inventory\ProductRepository;
use App\Services\Inventory\InventoryLocationAccessService;
use App\Services\Inventory\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function index(Request $request, InventoryLocationAccessService $locations): View
    {
        $warehouseIds = $locations->accessibleWarehouses($request->user())->pluck('id');

        return view('command-center.inventory.stock.adjustments.index', [
            'adjustments' => StockAdjustment::query()->with(['warehouse', 'creator', 'approver'])->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $warehouseIds)->latest()->paginate(15),
        ]);
    }

    public function create(Request $request, ProductRepository $products, InventoryLookupRepository $lookups, InventoryLocationAccessService $access): View
    {
        $options = $lookups->formOptions($request->user()->company_id);
        $warehouseIds = $access->accessibleWarehouses($request->user())->pluck('id');

        return view('command-center.inventory.stock.adjustments.create', [
            'products' => $products->activeForCompany($request->user()->company_id),
            'warehouses' => $options['warehouses']->whereIn('id', $warehouseIds)->values(),
            'locations' => $options['locations']->whereIn('warehouse_id', $warehouseIds)->values(),
        ]);
    }

    public function store(StockAdjustmentRequest $request, StockService $stockService): RedirectResponse
    {
        $adjustment = $stockService->createAdjustment($request->user(), $request->validated());

        return redirect()->route('inventory.adjustments.show', $adjustment)->with('status', 'Stock adjustment draft created.');
    }

    public function show(Request $request, InventoryLocationAccessService $locations, int $adjustment): View
    {
        $warehouseIds = $locations->accessibleWarehouses($request->user())->pluck('id');

        return view('command-center.inventory.stock.adjustments.show', [
            'adjustment' => StockAdjustment::query()->with(['warehouse', 'items.product', 'items.location', 'creator', 'approver'])->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $warehouseIds)->findOrFail($adjustment),
        ]);
    }

    public function approve(Request $request, StockService $stockService, InventoryLocationAccessService $locations, int $adjustment): RedirectResponse
    {
        $model = StockAdjustment::query()->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $locations->accessibleWarehouses($request->user())->pluck('id'))->findOrFail($adjustment);
        $stockService->approveAdjustment($model, $request->user());

        return back()->with('status', 'Stock adjustment approved.');
    }
}

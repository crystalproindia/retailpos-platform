<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Services\Inventory\InventoryLocationAccessService;
use App\Services\Inventory\InventoryStockViewService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockAvailabilityController extends Controller
{
    public function index(Request $request, InventoryStockViewService $stock, InventoryLocationAccessService $locations): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255'], 'warehouse_id' => ['nullable', 'integer']]);

        return view('command-center.inventory.stock.availability', ['products' => $stock->availability($request->user(), $filters), 'warehouses' => $locations->accessibleWarehouses($request->user(), false)]);
    }

    public function show(Request $request, InventoryStockViewService $stock, int $product): View
    {
        $model = Product::query()->with(['category', 'brand', 'unit', 'taxRate'])->where('company_id', $request->user()->company_id)->findOrFail($product);

        return view('command-center.inventory.stock.product-detail', ['product' => $model, 'inventory' => $stock->product($request->user(), $model)]);
    }
}

<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\OpeningStockRequest;
use App\Repositories\Inventory\InventoryLookupRepository;
use App\Repositories\Inventory\ProductRepository;
use App\Services\Inventory\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OpeningStockController extends Controller
{
    public function create(Request $request, ProductRepository $products, InventoryLookupRepository $lookups): View
    {
        $options = $lookups->formOptions($request->user()->company_id);

        return view('command-center.inventory.stock.opening', [
            'products' => $products->activeForCompany($request->user()->company_id),
            'warehouses' => $options['warehouses'],
            'locations' => $options['locations'],
        ]);
    }

    public function store(OpeningStockRequest $request, StockService $stockService): RedirectResponse
    {
        $stockService->recordOpeningStock($request->user(), $request->validated());

        return redirect()->route('inventory.stock.ledger')->with('status', 'Opening stock recorded.');
    }
}

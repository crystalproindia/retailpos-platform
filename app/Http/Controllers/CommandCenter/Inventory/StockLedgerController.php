<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Repositories\Inventory\InventoryLookupRepository;
use App\Repositories\Inventory\ProductRepository;
use App\Repositories\Inventory\StockRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockLedgerController extends Controller
{
    public function __invoke(Request $request, StockRepository $stocks, ProductRepository $products, InventoryLookupRepository $lookups): View
    {
        $options = $lookups->formOptions($request->user()->company_id);

        return view('command-center.inventory.stock.ledger', [
            'movements' => $stocks->ledger($request->user()->company_id, $request->only(['product_id', 'warehouse_id', 'movement_type', 'date_from', 'date_to'])),
            'products' => $products->activeForCompany($request->user()->company_id),
            'warehouses' => $options['warehouses'],
        ]);
    }
}

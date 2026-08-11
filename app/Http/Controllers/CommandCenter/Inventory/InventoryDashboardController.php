<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryDashboardService;
use App\Services\Inventory\InventoryLocationAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryDashboardController extends Controller
{
    public function __invoke(Request $request, InventoryDashboardService $dashboard, InventoryLocationAccessService $locations): View
    {
        $filters = $request->validate(['warehouse_id' => ['nullable', 'integer']]);

        return view('command-center.inventory.dashboard', [
            'dashboard' => $dashboard->metrics($request->user(), $filters),
            'warehouses' => $locations->accessibleWarehouses($request->user(), false),
        ]);
    }
}

<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryDashboardController extends Controller
{
    public function __invoke(Request $request, InventoryDashboardService $dashboard): View
    {
        return view('command-center.inventory.dashboard', [
            'dashboard' => $dashboard->metrics($request->user()->company_id),
        ]);
    }
}

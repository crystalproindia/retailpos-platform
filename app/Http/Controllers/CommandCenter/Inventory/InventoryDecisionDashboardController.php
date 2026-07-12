<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Purchases\InventoryDecisionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryDecisionDashboardController extends Controller
{
    public function __invoke(Request $request, InventoryDecisionService $dashboard): View
    {
        return view('command-center.inventory.decision.dashboard', [
            'dashboard' => $dashboard->metrics($request->user()->company_id),
        ]);
    }
}

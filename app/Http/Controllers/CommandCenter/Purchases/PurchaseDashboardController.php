<?php

namespace App\Http\Controllers\CommandCenter\Purchases;

use App\Http\Controllers\Controller;
use App\Services\Purchases\PurchaseDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseDashboardController extends Controller
{
    public function __invoke(Request $request, PurchaseDashboardService $dashboard): View
    {
        return view('command-center.purchases.dashboard', [
            'dashboard' => $dashboard->metrics($request->user()->company_id),
        ]);
    }
}

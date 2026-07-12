<?php

namespace App\Http\Controllers\CommandCenter\Purchases;

use App\Http\Controllers\Controller;
use App\Services\Purchases\SupplierDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierDashboardController extends Controller
{
    public function __invoke(Request $request, SupplierDashboardService $dashboard): View
    {
        return view('command-center.purchases.supplier-dashboard', [
            'dashboard' => $dashboard->metrics($request->user()->company_id),
        ]);
    }
}

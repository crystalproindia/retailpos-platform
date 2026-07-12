<?php

namespace App\Http\Controllers\CommandCenter\Promotions;

use App\Http\Controllers\Controller;
use App\Services\Promotions\PromotionDashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionDashboardController extends Controller
{
    public function __invoke(Request $request, PromotionDashboardService $dashboard): View { return view('command-center.promotions.dashboard', ['dashboard' => $dashboard->metrics($request->user()->company_id)]); }
}

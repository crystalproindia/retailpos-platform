<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Crm\CrmPipelineAnalyticsService;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmSalespersonPerformanceController extends Controller
{
    public function __invoke(Request $request, CrmPipelineAnalyticsService $analytics, OutletAccessService $outlets): View
    {
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer'], 'assigned_user_id' => ['nullable', 'integer'],
            'source_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $user = $request->user();

        return view('command-center.crm.analytics.salesperson-performance', [
            'performance' => $analytics->salespersonPerformance($user, $filters),
            'filters' => $filters,
            'outlets' => $outlets->accessibleOutlets($user),
            // The view exposes only people represented by already-authorized leads.
            'salespeople' => User::query()->whereIn('id', $analytics->salespersonPerformance($user)->pluck('salesperson_id')->filter())->orderBy('name')->get(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\CrmPipelineAnalyticsService;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmPipelineAnalyticsController extends Controller
{
    public function __invoke(Request $request, CrmPipelineAnalyticsService $analytics, OutletAccessService $outlets): View
    {
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer'], 'assigned_user_id' => ['nullable', 'integer'],
            'source_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return view('command-center.crm.analytics.index', [
            'analytics' => $analytics->summary($request->user(), $filters),
            'forecast' => $analytics->forecast($request->user(), $filters),
            'filters' => $filters,
            'outlets' => $outlets->accessibleOutlets($request->user()),
        ]);
    }
}

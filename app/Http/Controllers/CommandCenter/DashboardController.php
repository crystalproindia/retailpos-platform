<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Repositories\Crm\LeadRepository;
use App\Repositories\DashboardRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardRepository $dashboardRepository, LeadRepository $leadRepository): View
    {
        $user = $request->user();

        return view('command-center.dashboard', [
            'metrics' => $dashboardRepository->metricsFor($user),
            'leadMetrics' => $leadRepository->commandCenterMetrics($user),
            'recentAuditLogs' => AuditLog::query()
                ->with('user')
                ->where('company_id', $user->company_id)
                ->latest('created_at')
                ->limit(5)
                ->get(),
        ]);
    }
}

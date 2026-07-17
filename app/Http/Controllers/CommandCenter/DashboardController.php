<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Repositories\Crm\DemoScheduleRepository;
use App\Repositories\Crm\LeadRepository;
use App\Repositories\Crm\CrmOnboardingRepository;
use App\Repositories\Crm\CrmSupportTicketRepository;
use App\Repositories\DashboardRepository;
use App\Services\Cms\CmsWebsiteControlService;
use App\Services\Crm\CrmExecutiveReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardRepository $dashboardRepository, LeadRepository $leadRepository, DemoScheduleRepository $demoScheduleRepository, CrmOnboardingRepository $onboardings, CrmSupportTicketRepository $supportTickets, CmsWebsiteControlService $websiteControl, CrmExecutiveReportService $reports): View
    {
        $user = $request->user();

        return view('command-center.dashboard', [
            'metrics' => $dashboardRepository->metricsFor($user),
            'leadMetrics' => $leadRepository->commandCenterMetrics($user),
            'demoMetrics' => $demoScheduleRepository->dashboardMetrics($user),
            'upcomingDemos' => $demoScheduleRepository->upcomingForUser($user),
            'onboardingMetrics' => $user->can('crm.onboarding.view') ? $onboardings->dashboard($user) : null,
            'supportMetrics' => $user->can('crm.support.view') ? $supportTickets->dashboard($user) : null,
            'businessHealth' => $user->can('crm.reports.view') ? $reports->dashboard($user) : null,
            'cmsDashboard' => $user->can('cms.view') ? $websiteControl->dashboard($user->company_id) : null,
            'recentAuditLogs' => AuditLog::query()
                ->with('user')
                ->where('company_id', $user->company_id)
                ->latest('created_at')
                ->limit(5)
                ->get(),
        ]);
    }
}

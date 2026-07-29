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
use App\Services\Saas\Free365OnboardingService;
use App\Services\Saas\StoreSetupWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardRepository $dashboardRepository, LeadRepository $leadRepository, DemoScheduleRepository $demoScheduleRepository, CrmOnboardingRepository $onboardings, CrmSupportTicketRepository $supportTickets, CmsWebsiteControlService $websiteControl, CrmExecutiveReportService $reports, Free365OnboardingService $free365Onboarding, StoreSetupWizardService $storeSetup): View|RedirectResponse
    {
        $user = $request->user();
        if ($storeSetup->shouldRedirect($user)) return redirect()->route('onboarding.store-setup.show');

        return view('command-center.dashboard', [
            'metrics' => $dashboardRepository->metricsFor($user),
            'leadMetrics' => $leadRepository->commandCenterMetrics($user),
            'demoMetrics' => $demoScheduleRepository->dashboardMetrics($user),
            'upcomingDemos' => $demoScheduleRepository->upcomingForUser($user),
            'onboardingMetrics' => $user->can('crm.onboarding.view') ? $onboardings->dashboard($user) : null,
            'supportMetrics' => $user->can('crm.support.view') ? $supportTickets->dashboard($user) : null,
            'businessHealth' => $user->can('crm.reports.view') ? $reports->dashboard($user) : null,
            'cmsDashboard' => $user->can('cms.view') ? $websiteControl->dashboard($user->company_id) : null,
            'free365Onboarding' => $free365Onboarding->checklist($user),
            'storeSetupAvailable' => $storeSetup->enabled() && $storeSetup->canManage($user),
            'recentAuditLogs' => AuditLog::query()
                ->with('user')
                ->where('company_id', $user->company_id)
                ->latest('created_at')
                ->limit(5)
                ->get(),
        ]);
    }
}

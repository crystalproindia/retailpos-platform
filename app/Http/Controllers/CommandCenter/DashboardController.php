<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Repositories\Crm\CrmOnboardingRepository;
use App\Repositories\Crm\CrmSupportTicketRepository;
use App\Repositories\Crm\DemoScheduleRepository;
use App\Repositories\Crm\LeadRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\Tasks\TaskRepository;
use App\Services\Cms\CmsWebsiteControlService;
use App\Services\Crm\CrmExecutiveReportService;
use App\Services\Navigation\NavigationPreferenceService;
use App\Services\Outlets\OutletAccessService;
use App\Services\Saas\Free365OnboardingService;
use App\Services\Saas\StoreSetupWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardRepository $dashboardRepository, LeadRepository $leadRepository, DemoScheduleRepository $demoScheduleRepository, CrmOnboardingRepository $onboardings, CrmSupportTicketRepository $supportTickets, TaskRepository $tasks, CmsWebsiteControlService $websiteControl, CrmExecutiveReportService $reports, Free365OnboardingService $free365Onboarding, StoreSetupWizardService $storeSetup, NavigationPreferenceService $navigation, OutletAccessService $outlets): View|RedirectResponse
    {
        $user = $request->user();
        if ($storeSetup->shouldRedirect($user)) {
            return redirect()->route('onboarding.store-setup.show');
        }

        $availableOutlets = $outlets->accessibleOutlets($user);
        $currentOutlet = $availableOutlets->firstWhere('id', session('outlet_context_id'))
            ?? $availableOutlets->firstWhere('id', $user->branch_id)
            ?? $availableOutlets->firstWhere('is_primary', true)
            ?? $availableOutlets->first();

        return view('command-center.dashboard', [
            'metrics' => $dashboardRepository->metricsFor($user),
            'leadMetrics' => $leadRepository->commandCenterMetrics($user),
            'demoMetrics' => $demoScheduleRepository->dashboardMetrics($user),
            'upcomingDemos' => $demoScheduleRepository->upcomingForUser($user),
            'onboardingMetrics' => $user->can('crm.onboarding.view') ? $onboardings->dashboard($user) : null,
            'supportMetrics' => $user->can('crm.support.view') ? $supportTickets->dashboard($user) : null,
            'taskMetrics' => $user->can('tasks.view') ? ['personal' => $tasks->personalMetrics($user), 'work' => $tasks->workMetrics($user)] : null,
            'teamTaskMetrics' => $user->can('tasks.view_team') ? $tasks->teamMetrics($user) : null,
            'businessHealth' => $user->can('crm.reports.view') ? $reports->dashboard($user) : null,
            'cmsDashboard' => $user->can('cms.view') ? $websiteControl->dashboard($user->company_id) : null,
            'free365Onboarding' => $free365Onboarding->checklist($user),
            'storeSetupAvailable' => $storeSetup->enabled() && $storeSetup->canManage($user),
            'quickActions' => $navigation->quickActions($user),
            'pinnedModules' => $navigation->pinnedModules($user),
            'moduleGroups' => $navigation->visibleModules($user)->groupBy('category'),
            'currentOutlet' => $currentOutlet,
            'recentAuditLogs' => AuditLog::query()
                ->with('user')
                ->where('company_id', $user->company_id)
                ->latest('created_at')
                ->limit(5)
                ->get(),
        ]);
    }
}

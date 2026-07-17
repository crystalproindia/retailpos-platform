<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Repositories\Crm\DemoScheduleRepository;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Repositories\Crm\CrmOnboardingRepository;
use App\Repositories\Crm\CrmSupportTicketRepository;
use App\Repositories\Crm\LeadRepository;
use App\Repositories\Crm\QuotationRepository;
use App\Repositories\Crm\ProformaRepository;
use App\Services\Crm\CrmLeadScoringService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmDashboardController extends Controller
{
    public function __invoke(Request $request, LeadRepository $leadRepository, DemoScheduleRepository $demoScheduleRepository, QuotationRepository $quotationRepository, CrmCustomerRepository $customerRepository, ProformaRepository $proformas, CrmOnboardingRepository $onboardings, CrmSupportTicketRepository $supportTickets, CrmLeadScoringService $leadScoring): View
    {
        return view('command-center.crm.dashboard', [
            'metrics' => $leadRepository->dashboardMetrics($request->user()),
            'demoMetrics' => $demoScheduleRepository->dashboardMetrics($request->user()),
            'upcomingDemos' => $demoScheduleRepository->upcomingForUser($request->user()),
            'quotationMetrics' => $quotationRepository->dashboardMetrics($request->user()),
            'customerMetrics' => $customerRepository->dashboardMetrics($request->user()),
            'proformaMetrics' => $proformas->metrics($request->user()),
            'onboardingMetrics' => $request->user()->can('crm.onboarding.view') ? $onboardings->dashboard($request->user()) : null,
            'supportMetrics' => $request->user()->can('crm.support.view') ? $supportTickets->dashboard($request->user()) : null,
            'aiInsights' => $leadScoring->dashboardInsights($request->user()),
        ]);
    }
}

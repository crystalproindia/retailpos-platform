<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Repositories\Crm\DemoScheduleRepository;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Repositories\Crm\LeadRepository;
use App\Repositories\Crm\QuotationRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmDashboardController extends Controller
{
    public function __invoke(Request $request, LeadRepository $leadRepository, DemoScheduleRepository $demoScheduleRepository, QuotationRepository $quotationRepository, CrmCustomerRepository $customerRepository): View
    {
        return view('command-center.crm.dashboard', [
            'metrics' => $leadRepository->dashboardMetrics($request->user()),
            'demoMetrics' => $demoScheduleRepository->dashboardMetrics($request->user()),
            'upcomingDemos' => $demoScheduleRepository->upcomingForUser($request->user()),
            'quotationMetrics' => $quotationRepository->dashboardMetrics($request->user()),
            'customerMetrics' => $customerRepository->dashboardMetrics($request->user()),
        ]);
    }
}

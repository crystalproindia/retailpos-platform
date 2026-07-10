<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Repositories\Crm\LeadRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CrmDashboardController extends Controller
{
    public function __invoke(Request $request, LeadRepository $leadRepository): View
    {
        return view('command-center.crm.dashboard', [
            'metrics' => $leadRepository->dashboardMetrics($request->user()),
        ]);
    }
}

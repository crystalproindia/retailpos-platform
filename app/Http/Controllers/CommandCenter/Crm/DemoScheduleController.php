<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\DemoMeetingMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\RescheduleDemoScheduleRequest;
use App\Http\Requests\Crm\StoreDemoScheduleRequest;
use App\Repositories\Crm\DemoScheduleRepository;
use App\Repositories\Crm\LeadRepository;
use App\Services\Crm\DemoScheduleService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DemoScheduleController extends Controller
{
    public function create(Request $request, LeadRepository $leadRepository, int $lead): View
    {
        return view('command-center.crm.demos.form', [
            'lead' => $leadRepository->findForUser($request->user(), $lead),
            'demo' => null,
            'users' => $this->usersForCompany($request->user()->company_id),
            'meetingModes' => DemoMeetingMode::cases(),
        ]);
    }

    public function store(StoreDemoScheduleRequest $request, LeadRepository $leadRepository, DemoScheduleService $demoScheduleService, int $lead): RedirectResponse
    {
        $crmLead = $leadRepository->findForUser($request->user(), $lead);
        $demoScheduleService->schedule($crmLead, $request->user(), $request->validated());

        return redirect()->route('crm.leads.show', $crmLead)->with('status', 'Demo scheduled.');
    }

    public function edit(Request $request, DemoScheduleRepository $demoRepository, int $demo): View
    {
        $schedule = $demoRepository->findForUser($request->user(), $demo);

        return view('command-center.crm.demos.form', [
            'lead' => $schedule->lead,
            'demo' => $schedule,
            'users' => $this->usersForCompany($request->user()->company_id),
            'meetingModes' => DemoMeetingMode::cases(),
        ]);
    }

    public function reschedule(RescheduleDemoScheduleRequest $request, DemoScheduleRepository $demoRepository, DemoScheduleService $demoScheduleService, int $demo): RedirectResponse
    {
        $schedule = $demoScheduleService->reschedule(
            $demoRepository->findForUser($request->user(), $demo),
            $request->user(),
            $request->validated(),
        );

        return redirect()->route('crm.leads.show', $schedule->lead_id)->with('status', 'Demo rescheduled.');
    }

    public function complete(Request $request, DemoScheduleRepository $demoRepository, DemoScheduleService $demoScheduleService, int $demo): RedirectResponse
    {
        abort_unless($request->user()->can('crm.demos.complete'), 403);

        $schedule = $demoScheduleService->complete($demoRepository->findForUser($request->user(), $demo), $request->user());

        return redirect()->route('crm.leads.show', $schedule->lead_id)->with('status', 'Demo marked completed.');
    }

    public function cancel(Request $request, DemoScheduleRepository $demoRepository, DemoScheduleService $demoScheduleService, int $demo): RedirectResponse
    {
        abort_unless($request->user()->can('crm.demos.cancel'), 403);

        $schedule = $demoScheduleService->cancel($demoRepository->findForUser($request->user(), $demo), $request->user());

        return redirect()->route('crm.leads.show', $schedule->lead_id)->with('status', 'Demo cancelled.');
    }

    private function usersForCompany(int $companyId)
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}

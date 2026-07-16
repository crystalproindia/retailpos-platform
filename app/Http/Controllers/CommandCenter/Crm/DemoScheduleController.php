<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\DemoMeetingMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\RescheduleDemoScheduleRequest;
use App\Http\Requests\Crm\StoreDemoScheduleRequest;
use App\Repositories\Crm\DemoScheduleRepository;
use App\Repositories\Crm\LeadRepository;
use App\Services\Crm\DemoScheduleService;
use App\Services\Integrations\GoogleCalendarDemoSyncService;
use App\Services\Integrations\GoogleCalendarService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DemoScheduleController extends Controller
{
    public function create(Request $request, LeadRepository $leadRepository, GoogleCalendarService $googleCalendar, int $lead): View
    {
        return view('command-center.crm.demos.form', [
            'lead' => $leadRepository->findForUser($request->user(), $lead),
            'demo' => null,
            'users' => $this->usersForCompany($request->user()->company_id),
            'meetingModes' => DemoMeetingMode::cases(),
            'googleCalendarConnected' => $googleCalendar->isConfigured() && $googleCalendar->connectionForCompany($request->user()->company_id)?->isConnected(),
        ]);
    }

    public function store(StoreDemoScheduleRequest $request, LeadRepository $leadRepository, DemoScheduleService $demoScheduleService, GoogleCalendarService $googleCalendar, GoogleCalendarDemoSyncService $syncService, int $lead): RedirectResponse
    {
        $crmLead = $leadRepository->findForUser($request->user(), $lead);
        $schedule = $demoScheduleService->schedule($crmLead, $request->user(), $request->validated());
        $connected = $googleCalendar->isConfigured() && $googleCalendar->connectionForCompany($request->user()->company_id)?->isConnected();

        if ($request->boolean('sync_to_google') && $connected) {
            $result = $syncService->sync(
                $schedule,
                $request->user(),
                $request->boolean('create_google_meet') && $schedule->meeting_mode === DemoMeetingMode::GoogleMeetLater,
            );

            return redirect()->route('crm.leads.show', $crmLead)
                ->with($result->succeeded ? 'status' : 'error', $result->succeeded ? 'Demo scheduled and synced to Google Calendar.' : 'Demo scheduled internally. '.$result->message);
        }

        $message = $connected
            ? 'Demo scheduled internally.'
            : 'Demo scheduled internally. Connect Google Calendar to sync events.';

        return redirect()->route('crm.leads.show', $crmLead)->with('status', $message);
    }

    public function edit(Request $request, DemoScheduleRepository $demoRepository, GoogleCalendarService $googleCalendar, int $demo): View
    {
        $schedule = $demoRepository->findForUser($request->user(), $demo);

        return view('command-center.crm.demos.form', [
            'lead' => $schedule->lead,
            'demo' => $schedule,
            'users' => $this->usersForCompany($request->user()->company_id),
            'meetingModes' => DemoMeetingMode::cases(),
            'googleCalendarConnected' => $googleCalendar->isConfigured() && $googleCalendar->connectionForCompany($request->user()->company_id)?->isConnected(),
        ]);
    }

    public function reschedule(RescheduleDemoScheduleRequest $request, DemoScheduleRepository $demoRepository, DemoScheduleService $demoScheduleService, GoogleCalendarDemoSyncService $syncService, int $demo): RedirectResponse
    {
        $schedule = $demoScheduleService->reschedule(
            $demoRepository->findForUser($request->user(), $demo),
            $request->user(),
            $request->validated(),
        );

        $result = $syncService->updateIfSynced(
            $schedule,
            $request->user(),
            $request->boolean('create_google_meet') && $schedule->meeting_mode === DemoMeetingMode::GoogleMeetLater,
        );

        return redirect()->route('crm.leads.show', $schedule->lead_id)
            ->with($result && ! $result->succeeded ? 'error' : 'status', $result ? 'Demo rescheduled. '.$result->message : 'Demo rescheduled.');
    }

    public function complete(Request $request, DemoScheduleRepository $demoRepository, DemoScheduleService $demoScheduleService, int $demo): RedirectResponse
    {
        abort_unless($request->user()->can('crm.demos.complete'), 403);

        $schedule = $demoScheduleService->complete($demoRepository->findForUser($request->user(), $demo), $request->user());

        return redirect()->route('crm.leads.show', $schedule->lead_id)->with('status', 'Demo marked completed.');
    }

    public function cancel(Request $request, DemoScheduleRepository $demoRepository, DemoScheduleService $demoScheduleService, GoogleCalendarDemoSyncService $syncService, int $demo): RedirectResponse
    {
        abort_unless($request->user()->can('crm.demos.cancel'), 403);

        $schedule = $demoScheduleService->cancel($demoRepository->findForUser($request->user(), $demo), $request->user());

        $result = $syncService->cancelIfSynced($schedule, $request->user());

        return redirect()->route('crm.leads.show', $schedule->lead_id)
            ->with($result && ! $result->succeeded ? 'error' : 'status', $result ? 'Demo cancelled. '.$result->message : 'Demo cancelled.');
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

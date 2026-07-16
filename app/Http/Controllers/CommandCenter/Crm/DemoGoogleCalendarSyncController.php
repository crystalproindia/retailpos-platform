<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\DemoMeetingMode;
use App\Http\Controllers\Controller;
use App\Repositories\Crm\DemoScheduleRepository;
use App\Services\Integrations\GoogleCalendarDemoSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoGoogleCalendarSyncController extends Controller
{
    public function __invoke(Request $request, DemoScheduleRepository $demoRepository, GoogleCalendarDemoSyncService $syncService, int $demo): RedirectResponse
    {
        abort_unless($request->user()->can('crm.demos.sync_calendar'), 403);

        $schedule = $demoRepository->findForUser($request->user(), $demo);
        if (! $schedule->isActive()) {
            return redirect()->route('crm.leads.show', $schedule->lead_id)->with('error', 'Only active demos can be synced to Google Calendar.');
        }
        $result = $syncService->sync(
            $schedule,
            $request->user(),
            $request->boolean('create_google_meet') && $schedule->meeting_mode === DemoMeetingMode::GoogleMeetLater,
        );

        return redirect()->route('crm.leads.show', $schedule->lead_id)
            ->with($result->succeeded ? 'status' : 'error', $result->message);
    }
}

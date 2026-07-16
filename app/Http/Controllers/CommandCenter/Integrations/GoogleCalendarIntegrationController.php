<?php

namespace App\Http\Controllers\CommandCenter\Integrations;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Integrations\GoogleCalendarException;
use App\Services\Integrations\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoogleCalendarIntegrationController extends Controller
{
    public function index(Request $request, GoogleCalendarService $googleCalendar): View
    {
        return view('command-center.integrations.google.index', [
            'configured' => $googleCalendar->isConfigured(),
            'connection' => $googleCalendar->connectionForCompany($request->user()->company_id),
        ]);
    }

    public function connect(Request $request, GoogleCalendarService $googleCalendar): RedirectResponse
    {
        abort_unless($request->user()->can('integrations.google.connect'), 403);

        try {
            return redirect()->away($googleCalendar->authorizationUrl($request->user()));
        } catch (GoogleCalendarException $exception) {
            return redirect()->route('integrations.google.index')->with('error', $exception->getMessage());
        }
    }

    public function callback(Request $request, GoogleCalendarService $googleCalendar, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->can('integrations.google.connect'), 403);

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('integrations.google.index')->with('error', 'Google Calendar connection was not completed.');
        }

        try {
            $connection = $googleCalendar->handleCallback($request->user(), (string) $request->string('code'), $request->input('state'));
            $auditLogger->record('integrations.google_calendar.connected', $connection, 'Google Calendar connected', [
                'company_id' => $request->user()->company_id,
                'account_email' => $connection->account_email,
            ]);

            return redirect()->route('integrations.google.index')->with('status', 'Google Calendar connected.');
        } catch (GoogleCalendarException $exception) {
            return redirect()->route('integrations.google.index')->with('error', $exception->getMessage());
        }
    }

    public function disconnect(Request $request, GoogleCalendarService $googleCalendar, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($request->user()->can('integrations.google.disconnect'), 403);

        try {
            $connection = $googleCalendar->disconnect($request->user());
            $auditLogger->record('integrations.google_calendar.disconnected', $connection, 'Google Calendar disconnected', [
                'company_id' => $request->user()->company_id,
            ]);

            return redirect()->route('integrations.google.index')->with('status', 'Google Calendar disconnected.');
        } catch (GoogleCalendarException $exception) {
            return redirect()->route('integrations.google.index')->with('error', $exception->getMessage());
        }
    }

    public function test(Request $request, GoogleCalendarService $googleCalendar): RedirectResponse
    {
        abort_unless($request->user()->can('integrations.google.connect'), 403);

        try {
            $connection = $googleCalendar->connectionForCompany($request->user()->company_id);

            if (! $connection) {
                throw new GoogleCalendarException('Connect Google Calendar before testing the connection.');
            }

            $googleCalendar->testConnection($connection);

            return redirect()->route('integrations.google.index')->with('status', 'Google Calendar connection is working.');
        } catch (GoogleCalendarException $exception) {
            return redirect()->route('integrations.google.index')->with('error', $exception->getMessage());
        }
    }

    public function updateSettings(Request $request, GoogleCalendarService $googleCalendar): RedirectResponse
    {
        abort_unless($request->user()->can('integrations.google.connect'), 403);

        $validated = $request->validate([
            'calendar_id' => ['required', 'string', 'max:255'],
        ]);

        try {
            $googleCalendar->updateSettings($request->user(), $validated);

            return redirect()->route('integrations.google.index')->with('status', 'Google Calendar settings updated.');
        } catch (GoogleCalendarException $exception) {
            return redirect()->route('integrations.google.index')->with('error', $exception->getMessage());
        }
    }
}

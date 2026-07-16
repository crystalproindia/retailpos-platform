<?php

namespace App\Services\Integrations;

use App\Models\Crm\DemoSchedule;
use App\Models\IntegrationConnection;
use App\Models\User;
use App\Repositories\Integrations\IntegrationConnectionRepository;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleCalendarService
{
    private const AUTHORIZATION_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

    private const CALENDAR_BASE_URL = 'https://www.googleapis.com/calendar/v3';

    public function __construct(private readonly IntegrationConnectionRepository $connections) {}

    public function isConfigured(): bool
    {
        return filled(config('services.google_calendar.client_id'))
            && filled(config('services.google_calendar.client_secret'))
            && filled(config('services.google_calendar.redirect_uri'));
    }

    public function connectionForCompany(int $companyId): ?IntegrationConnection
    {
        return $this->connections->googleCalendarForCompany($companyId);
    }

    public function authorizationUrl(User $user): string
    {
        $this->ensureConfigured();

        $state = Str::random(64);
        session()->put([
            'google_calendar_oauth_state' => $state,
            'google_calendar_oauth_company_id' => $user->company_id,
        ]);

        return self::AUTHORIZATION_URL.'?'.http_build_query([
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => config('services.google_calendar.redirect_uri'),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'scope' => implode(' ', $this->scopes()),
            'state' => $state,
        ]);
    }

    public function handleCallback(User $user, string $code, ?string $state): IntegrationConnection
    {
        $expectedState = session()->pull('google_calendar_oauth_state');
        $companyId = session()->pull('google_calendar_oauth_company_id');

        if (! is_string($expectedState) || ! is_string($state) || ! hash_equals($expectedState, $state) || (int) $companyId !== $user->company_id) {
            throw new GoogleCalendarException('Google Calendar authorization could not be verified. Please try connecting again.');
        }

        $this->ensureConfigured();
        $token = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => config('services.google_calendar.client_id'),
                'client_secret' => config('services.google_calendar.client_secret'),
                'redirect_uri' => config('services.google_calendar.redirect_uri'),
                'grant_type' => 'authorization_code',
            ]);

        if (! $token->successful() || blank($token->json('access_token'))) {
            throw new GoogleCalendarException('Google Calendar could not complete the connection. Please try again.');
        }

        $existing = $this->connectionForCompany($user->company_id);
        $accessToken = (string) $token->json('access_token');

        return $this->connections->updateGoogleCalendar($user, [
            'account_email' => $this->accountEmail($accessToken),
            'access_token' => $accessToken,
            'refresh_token' => $token->json('refresh_token') ?: $existing?->refresh_token,
            'token_expires_at' => $this->expiryFrom($token->json('expires_in')),
            'scopes' => $this->scopesFrom($token->json('scope')),
            'settings' => $existing?->settings ?: ['calendar_id' => 'primary'],
            'status' => 'connected',
            'connected_by' => $user->id,
            'connected_at' => now(),
            'last_synced_at' => null,
            'disconnected_at' => null,
        ]);
    }

    public function disconnect(User $user): IntegrationConnection
    {
        $connection = $this->connectionForCompany($user->company_id);

        if (! $connection) {
            throw new GoogleCalendarException('No Google Calendar connection was found.');
        }

        $connection->update([
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'status' => 'disconnected',
            'disconnected_at' => now(),
        ]);

        return $connection->refresh();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function updateSettings(User $user, array $settings): IntegrationConnection
    {
        $connection = $this->connectionForCompany($user->company_id);

        if (! $connection) {
            throw new GoogleCalendarException('Connect Google Calendar before changing calendar settings.');
        }

        $connection->update([
            'settings' => array_merge($connection->settings ?? [], $settings),
        ]);

        return $connection->refresh();
    }

    public function testConnection(IntegrationConnection $connection): void
    {
        $response = $this->clientFor($connection)->get($this->calendarUrl($connection));

        if (! $response->successful()) {
            throw new GoogleCalendarException('Google Calendar could not be reached. Check the connected account and calendar ID.');
        }
    }

    public function markSynced(int $companyId): void
    {
        $this->connectionForCompany($companyId)?->update(['last_synced_at' => now()]);
    }

    /**
     * @return array{event_id: string, event_url: ?string, meeting_link: ?string}
     */
    public function syncDemo(DemoSchedule $demo, bool $createMeet): array
    {
        $connection = $this->connectedConnection($demo->company_id);
        $request = $this->clientFor($connection);
        $url = $this->calendarUrl($connection).'/events';
        $payload = $this->eventPayload($demo, $createMeet);

        if ($demo->external_calendar_event_id) {
            $url .= '/'.$demo->external_calendar_event_id;
            $response = $request->put($url.($createMeet ? '?conferenceDataVersion=1' : ''), $payload);
        } else {
            $response = $request->post($url.($createMeet ? '?conferenceDataVersion=1' : ''), $payload);
        }

        if (! $response->successful() || blank($response->json('id'))) {
            throw new GoogleCalendarException('Google Calendar could not sync this demo. The internal demo schedule is unchanged.');
        }

        $meetingLink = $response->json('hangoutLink') ?: $this->conferenceLink($response->json('conferenceData.entryPoints', []));

        return [
            'event_id' => (string) $response->json('id'),
            'event_url' => $response->json('htmlLink'),
            'meeting_link' => $meetingLink,
        ];
    }

    public function cancelDemo(DemoSchedule $demo): void
    {
        if (blank($demo->external_calendar_event_id)) {
            return;
        }

        $connection = $this->connectedConnection($demo->company_id);
        $response = $this->clientFor($connection)
            ->delete($this->calendarUrl($connection).'/events/'.$demo->external_calendar_event_id);

        if (! $response->successful() && $response->status() !== 404) {
            throw new GoogleCalendarException('Google Calendar could not cancel this event. The internal demo remains cancelled.');
        }
    }

    private function connectedConnection(int $companyId): IntegrationConnection
    {
        $connection = $this->connectionForCompany($companyId);

        if (! $connection?->isConnected()) {
            throw new GoogleCalendarException('Connect Google Calendar before syncing demos.');
        }

        return $connection;
    }

    private function clientFor(IntegrationConnection $connection): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(15)
            ->withToken($this->accessToken($connection));
    }

    private function accessToken(IntegrationConnection $connection): string
    {
        if ($connection->token_expires_at?->isAfter(now()->addMinute()) && filled($connection->access_token)) {
            return $connection->access_token;
        }

        if (blank($connection->refresh_token)) {
            throw new GoogleCalendarException('Google Calendar needs to be reconnected before demos can sync.');
        }

        $this->ensureConfigured();
        $response = Http::asForm()
            ->acceptJson()
            ->timeout(15)
            ->post(self::TOKEN_URL, [
                'client_id' => config('services.google_calendar.client_id'),
                'client_secret' => config('services.google_calendar.client_secret'),
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            throw new GoogleCalendarException('Google Calendar needs to be reconnected before demos can sync.');
        }

        $connection->update([
            'access_token' => $response->json('access_token'),
            'token_expires_at' => $this->expiryFrom($response->json('expires_in')),
            'scopes' => $this->scopesFrom($response->json('scope')) ?: $connection->scopes,
        ]);

        return $connection->refresh()->access_token;
    }

    private function calendarUrl(IntegrationConnection $connection): string
    {
        $calendarId = $connection->settings['calendar_id'] ?? 'primary';

        return self::CALENDAR_BASE_URL.'/calendars/'.rawurlencode($calendarId);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(DemoSchedule $demo, bool $createMeet): array
    {
        $lead = $demo->lead;
        $attendees = collect([$demo->customer_email, $demo->assignedTo?->email])
            ->filter(fn (?string $email): bool => filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->map(fn (string $email): array => ['email' => $email])
            ->values()
            ->all();
        $payload = [
            'summary' => 'RetailPOS Demo - '.($lead?->business_name ?: $lead?->contact_name ?: $lead?->title ?: 'Lead'),
            'description' => $this->description($demo),
            'start' => $this->eventDateTime($demo->starts_at, $demo->timezone),
            'end' => $this->eventDateTime($demo->ends_at, $demo->timezone),
            'attendees' => $attendees,
        ];

        if ($createMeet) {
            $payload['conferenceData'] = [
                'createRequest' => [
                    'requestId' => 'retailpos-demo-'.$demo->id.'-'.Str::uuid(),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ];
        }

        return $payload;
    }

    private function description(DemoSchedule $demo): string
    {
        $lead = $demo->lead;

        return implode("\n", array_filter([
            'Lead: '.($lead?->contact_name ?: $lead?->title ?: 'Not provided'),
            'Company: '.($lead?->business_name ?: 'Not provided'),
            'Phone: '.($demo->customer_phone ?: $lead?->phone ?: 'Not provided'),
            'Email: '.($demo->customer_email ?: $lead?->email ?: 'Not provided'),
            'Business type: '.($lead?->business_type ?: 'Not provided'),
            filled($lead?->description) ? 'Requirement: '.$lead->description : null,
            'CRM lead: '.route('crm.leads.show', $demo->lead_id),
            filled($demo->notes) ? 'Notes: '.$demo->notes : null,
        ]));
    }

    /**
     * @return array{dateTime: string, timeZone: string}
     */
    private function eventDateTime(?CarbonInterface $dateTime, string $timezone): array
    {
        return [
            'dateTime' => $dateTime?->setTimezone($timezone)->toRfc3339String(),
            'timeZone' => $timezone,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entryPoints
     */
    private function conferenceLink(array $entryPoints): ?string
    {
        return data_get(Arr::first($entryPoints, fn (array $entryPoint): bool => ($entryPoint['entryPointType'] ?? null) === 'video'), 'uri');
    }

    private function accountEmail(string $accessToken): ?string
    {
        $response = Http::acceptJson()->timeout(10)->withToken($accessToken)->get(self::USERINFO_URL);

        $email = $response->successful() ? $response->json('email') : null;

        return is_string($email) ? $email : null;
    }

    private function expiryFrom(mixed $seconds): CarbonInterface
    {
        return now()->addSeconds(max(60, (int) $seconds ?: 3600));
    }

    /**
     * @return array<int, string>
     */
    private function scopesFrom(mixed $scopes): array
    {
        return is_string($scopes) && filled($scopes)
            ? array_values(array_filter(preg_split('/\s+/', trim($scopes)) ?: []))
            : $this->scopes();
    }

    /**
     * @return array<int, string>
     */
    private function scopes(): array
    {
        return [
            'https://www.googleapis.com/auth/calendar.events',
            'openid',
            'email',
        ];
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new GoogleCalendarException('Google Calendar credentials are not configured for this environment.');
        }
    }
}

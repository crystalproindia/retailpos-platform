<?php

namespace Tests\Feature;

use App\Enums\Crm\DemoMeetingMode;
use App\Enums\Crm\DemoScheduleStatus;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Crm\DemoSchedule;
use App\Models\IntegrationConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google_calendar.client_id' => 'google-client-id',
            'services.google_calendar.client_secret' => 'google-client-secret',
            'services.google_calendar.redirect_uri' => 'https://app.retailpos.biz/integrations/google/callback',
        ]);
    }

    public function test_authorized_administrator_can_view_google_calendar_integration_and_sales_cannot_connect(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $sales = $this->user(UserRole::Sales, $administrator->company, $administrator->branch);

        $this->actingAs($administrator)
            ->get('/integrations/google')
            ->assertOk()
            ->assertSee('Google Calendar Integration')
            ->assertSee('Not connected');

        $this->actingAs($sales)->get('/integrations/google')->assertForbidden();
        $this->actingAs($sales)->get('/integrations/google/connect')->assertForbidden();
    }

    public function test_oauth_callback_stores_an_encrypted_google_connection(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token-value',
                'refresh_token' => 'refresh-token-value',
                'expires_in' => 3600,
                'scope' => 'openid email https://www.googleapis.com/auth/calendar.events',
            ]),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(['email' => 'calendar@example.test']),
        ]);

        $this->actingAs($administrator)
            ->get('/integrations/google/connect')
            ->assertRedirectContains('accounts.google.com');

        $state = session('google_calendar_oauth_state');

        $this->actingAs($administrator)
            ->get('/integrations/google/callback?code=authorization-code&state='.$state)
            ->assertRedirect('/integrations/google')
            ->assertSessionHas('status', 'Google Calendar connected.');

        $connection = IntegrationConnection::query()->firstOrFail();

        $this->assertSame('google_calendar', $connection->provider);
        $this->assertSame('calendar@example.test', $connection->account_email);
        $this->assertSame('access-token-value', $connection->access_token);
        $this->assertSame('refresh-token-value', $connection->refresh_token);
        $this->assertNotSame('access-token-value', $connection->getRawOriginal('access_token'));
        $this->assertNotSame('refresh-token-value', $connection->getRawOriginal('refresh_token'));
    }

    public function test_google_credentials_missing_shows_a_safe_disabled_state(): void
    {
        config([
            'services.google_calendar.client_id' => null,
            'services.google_calendar.client_secret' => null,
        ]);
        $administrator = $this->user(UserRole::Administrator);

        $this->actingAs($administrator)
            ->get('/integrations/google')
            ->assertOk()
            ->assertSee('credentials are not configured')
            ->assertSee('Connect Google', false);
    }

    public function test_demo_sync_creates_calendar_event_meet_link_activity_audit_and_notification(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $lead = $this->lead($administrator);
        $demo = $this->demo($administrator, $lead, DemoMeetingMode::GoogleMeetLater);
        $this->connectedGoogleCalendar($administrator);
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'id' => 'google-event-123',
                'htmlLink' => 'https://calendar.google.com/event?eid=google-event-123',
                'hangoutLink' => 'https://meet.google.com/retailpos-demo',
            ], 200),
        ]);

        $this->actingAs($administrator)
            ->post("/crm/demos/{$demo->id}/sync-google-calendar", ['create_google_meet' => true])
            ->assertRedirect("/crm/leads/{$lead->id}")
            ->assertSessionHas('status', 'Demo synced to Google Calendar.');

        $demo->refresh();
        $this->assertSame('google', $demo->external_calendar_provider);
        $this->assertSame('google-event-123', $demo->external_calendar_event_id);
        $this->assertSame('synced', $demo->calendar_sync_status);
        $this->assertSame('https://meet.google.com/retailpos-demo', $demo->meeting_link);
        $this->assertSame('https://meet.google.com/retailpos-demo', $demo->external_meeting_link);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => 'Demo synced to Google Calendar']);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => 'Google Meet link created']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.demo.google_calendar_synced', 'auditable_id' => $demo->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.demo.google_meet_created', 'auditable_id' => $demo->id]);
        $this->assertTrue($administrator->notifications()->where('data->event_key', 'crm.demo.google_calendar_synced')->exists());
    }

    public function test_expired_access_token_is_refreshed_before_syncing_a_demo(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $lead = $this->lead($administrator);
        $demo = $this->demo($administrator, $lead);
        $connection = $this->connectedGoogleCalendar($administrator);
        $connection->update(['token_expires_at' => now()->subMinute()]);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'refreshed-access-token',
                'expires_in' => 3600,
            ]),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response([
                'id' => 'google-event-refreshed',
                'htmlLink' => 'https://calendar.google.com/event?eid=google-event-refreshed',
            ]),
        ]);

        $this->actingAs($administrator)
            ->post("/crm/demos/{$demo->id}/sync-google-calendar")
            ->assertRedirect("/crm/leads/{$lead->id}");

        $this->assertSame('refreshed-access-token', $connection->refresh()->access_token);
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST' && $request->url() === 'https://oauth2.googleapis.com/token');
    }

    public function test_google_sync_failure_keeps_internal_demo_and_creates_activity_audit_and_notification(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $lead = $this->lead($administrator);
        $demo = $this->demo($administrator, $lead);
        $this->connectedGoogleCalendar($administrator);
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events*' => Http::response(['error' => ['message' => 'provider failure']], 500),
        ]);

        $this->actingAs($administrator)
            ->post("/crm/demos/{$demo->id}/sync-google-calendar")
            ->assertRedirect("/crm/leads/{$lead->id}")
            ->assertSessionHas('error', 'Google Calendar could not sync this demo. The internal demo schedule is unchanged.');

        $demo->refresh();
        $this->assertSame(DemoScheduleStatus::Scheduled, $demo->status);
        $this->assertSame('failed', $demo->calendar_sync_status);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => 'Google Calendar sync failed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.demo.google_calendar_sync_failed', 'auditable_id' => $demo->id]);
        $this->assertTrue($administrator->notifications()->where('data->event_key', 'crm.demo.google_calendar_sync_failed')->exists());
        $this->assertSame(1, $demo->calendar_sync_attempts);
        $this->assertNotEmpty($demo->calendar_sync_error);
    }

    public function test_rescheduled_and_cancelled_synced_demos_update_google_calendar_without_breaking_internal_lifecycle(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $lead = $this->lead($administrator);
        $demo = $this->demo($administrator, $lead);
        $this->connectedGoogleCalendar($administrator);
        $demo->update([
            'external_calendar_provider' => 'google',
            'external_calendar_event_id' => 'google-event-456',
            'calendar_sync_status' => 'synced',
        ]);
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/google-event-456*' => Http::response([
                'id' => 'google-event-456',
                'htmlLink' => 'https://calendar.google.com/event?eid=google-event-456',
            ], 200),
        ]);

        $this->actingAs($administrator)
            ->patch("/crm/demos/{$demo->id}/reschedule", $this->schedulePayload($administrator, [
                'demo_date' => '2026-07-24',
                'start_time' => '14:00',
                'end_time' => '14:30',
            ]))
            ->assertRedirect("/crm/leads/{$lead->id}");

        $this->assertSame(DemoScheduleStatus::Rescheduled, $demo->refresh()->status);
        $this->assertSame('synced', $demo->calendar_sync_status);
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'PUT' && str_contains($request->url(), 'google-event-456'));

        $this->actingAs($administrator)
            ->post("/crm/demos/{$demo->id}/cancel")
            ->assertRedirect("/crm/leads/{$lead->id}");

        $this->assertSame(DemoScheduleStatus::Cancelled, $demo->refresh()->status);
        $this->assertSame('cancelled', $demo->calendar_sync_status);
        Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'DELETE' && str_contains($request->url(), 'google-event-456'));
    }

    public function test_local_conflict_is_rejected_and_unconfigured_google_falls_back_safely(): void
    {
        config(['services.google_calendar.client_id' => null, 'services.google_calendar.client_secret' => null]);
        $administrator = $this->user(UserRole::Administrator);
        $lead = $this->lead($administrator);
        $this->demo($administrator, $lead);

        $this->actingAs($administrator)
            ->post("/crm/leads/{$lead->id}/demos", $this->schedulePayload($administrator))
            ->assertSessionHasErrors('start_time');

        $this->assertDatabaseCount('demo_schedules', 1);

        $this->actingAs($administrator)
            ->post("/crm/leads/{$lead->id}/demos", $this->schedulePayload($administrator, ['start_time' => '11:00', 'end_time' => '11:30']))
            ->assertRedirect("/crm/leads/{$lead->id}");

        $this->assertDatabaseHas('demo_schedules', ['company_id' => $administrator->company_id, 'calendar_sync_status' => 'skipped_not_configured']);
    }

    private function connectedGoogleCalendar(User $user): IntegrationConnection
    {
        return IntegrationConnection::create([
            'company_id' => $user->company_id,
            'provider' => 'google_calendar',
            'name' => 'Google Calendar',
            'account_email' => 'calendar@example.test',
            'access_token' => 'valid-access-token',
            'refresh_token' => 'valid-refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/calendar.events'],
            'settings' => ['calendar_id' => 'primary'],
            'status' => 'connected',
            'connected_by' => $user->id,
            'connected_at' => now(),
        ]);
    }

    private function demo(User $user, CrmLead $lead, DemoMeetingMode $meetingMode = DemoMeetingMode::PhoneCall): DemoSchedule
    {
        return DemoSchedule::create([
            'company_id' => $user->company_id,
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'scheduled_by' => $user->id,
            'title' => 'Demo: '.$lead->title,
            'scheduled_date' => '2026-07-20',
            'starts_at' => '2026-07-20 10:00:00',
            'ends_at' => '2026-07-20 10:30:00',
            'timezone' => 'UTC',
            'meeting_mode' => $meetingMode,
            'customer_email' => $lead->email,
            'customer_phone' => $lead->phone,
            'notes' => 'Review multi-branch operations.',
            'status' => DemoScheduleStatus::Scheduled,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function schedulePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'demo_date' => '2026-07-20',
            'start_time' => '10:00',
            'end_time' => '10:30',
            'timezone' => 'UTC',
            'meeting_mode' => DemoMeetingMode::PhoneCall->value,
            'meeting_link' => null,
            'assigned_to' => $user->id,
            'customer_email' => 'demo@example.test',
            'customer_phone' => '+91 90000 11111',
            'notes' => 'Prepare branch and product questions.',
        ], $overrides);
    }

    private function lead(User $user): CrmLead
    {
        $source = CrmLeadSource::firstOrCreate([
            'company_id' => $user->company_id,
            'slug' => 'book-demo',
        ], [
            'name' => 'Book Demo',
            'is_active' => true,
        ]);
        $status = CrmLeadStatus::firstOrCreate([
            'company_id' => $user->company_id,
            'slug' => 'new',
        ], [
            'name' => 'New',
            'stage_type' => LeadStageType::New,
            'probability' => 10,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return CrmLead::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'source_id' => $source->id,
            'status_id' => $status->id,
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Google Calendar Demo Lead',
            'business_name' => 'Calendar Retail',
            'contact_name' => 'Asha Mehta',
            'email' => 'asha@example.test',
            'phone' => '+91 90000 11111',
            'business_type' => 'Retail',
            'description' => 'Need a multi-branch POS demonstration.',
            'priority' => LeadPriority::Medium,
        ]);
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => $role,
        ]);
    }
}

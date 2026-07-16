<?php

namespace Tests\Feature;

use App\Contracts\Events\DomainEvent;
use App\Contracts\Notifications\NotificationChannel;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Jobs\Notifications\SendNotificationDeliveryJob;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\DomainEventLog;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\Notifications\LeadNotificationSettings;
use App\Services\Notifications\Channels\DatabaseNotificationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class LeadNotificationAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_demo_request_notifies_administrators_and_the_assigned_sales_user(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $sales = $this->user(UserRole::Sales, $administrator->company, $administrator->branch);
        $staff = $this->user(UserRole::Staff, $administrator->company, $administrator->branch);
        $this->configurePublicIntake($sales);

        $this->withHeader('X-RetailPOS-Lead-Token', 'test-lead-token')
            ->postJson('/api/public/leads', $this->leadPayload(['source' => 'book_demo']))
            ->assertOk();

        $lead = CrmLead::query()->firstOrFail();

        $this->assertTrue($administrator->notifications()->where('data->aggregate_id', $lead->id)->exists());
        $this->assertTrue($sales->notifications()->where('data->aggregate_id', $lead->id)->exists());
        $this->assertFalse($staff->notifications()->where('data->aggregate_id', $lead->id)->exists());
        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'crm.lead.created',
            'channel' => 'database',
            'user_id' => $sales->id,
            'status' => 'delivered',
        ]);

        $notification = $sales->notifications()->where('data->aggregate_id', $lead->id)->firstOrFail();
        $this->assertSame('demo_request_received', $notification->data['metadata']['notification_type']);
        $this->assertSame('New demo request', $notification->data['title']);

        $this->actingAs($sales)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('1 unread notification');
    }

    public function test_public_lead_without_an_assigned_sales_user_notifies_default_sales_users(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $sales = $this->user(UserRole::Sales, $administrator->company, $administrator->branch);
        config()->set('services.retailpos.public_lead_token', 'test-lead-token');
        config()->set('services.retailpos.public_lead_company_id', $administrator->company_id);
        config()->set('services.retailpos.public_lead_assignee_id', null);

        $this->withHeader('X-RetailPOS-Lead-Token', 'test-lead-token')
            ->postJson('/api/public/leads', $this->leadPayload())
            ->assertOk();

        $this->assertTrue($administrator->notifications()->where('data->event_key', 'crm.lead.created')->exists());
        $this->assertTrue($sales->notifications()->where('data->event_key', 'crm.lead.created')->exists());
    }

    public function test_configured_lead_email_is_queued_with_a_source_specific_subject(): void
    {
        Queue::fake();

        $administrator = $this->user(UserRole::Administrator);
        $this->configurePublicIntake($administrator);
        config()->set('services.retailpos.lead_notifications.lead_email_notifications_enabled', true);
        config()->set('services.retailpos.lead_notifications.lead_notification_email', 'sales@retailpos.test');

        $this->withHeader('X-RetailPOS-Lead-Token', 'test-lead-token')
            ->postJson('/api/public/leads', $this->leadPayload(['source' => 'pricing_enquiry']))
            ->assertOk();

        $delivery = NotificationDelivery::query()
            ->where('event_key', 'crm.lead.created')
            ->where('channel', 'email')
            ->where('recipient', 'sales@retailpos.test')
            ->firstOrFail();

        $this->assertSame('queued', $delivery->status);
        $this->assertSame('New RetailPOS Pricing Enquiry', $delivery->payload['title']);
        $this->assertStringContainsString('Business type: Not provided', $delivery->payload['message']);
        $this->assertStringContainsString('Source: Pricing Enquiry', $delivery->payload['message']);
        $this->assertSame('New pricing enquiry', $administrator->notifications()->firstOrFail()->data['title']);
        Queue::assertPushed(SendNotificationDeliveryJob::class, fn (SendNotificationDeliveryJob $job): bool => $job->deliveryId === $delivery->id);
    }

    public function test_lead_email_delivery_is_not_queued_when_it_is_disabled(): void
    {
        Queue::fake();

        $administrator = $this->user(UserRole::Administrator);
        $this->configurePublicIntake($administrator);
        config()->set('services.retailpos.lead_notifications.lead_email_notifications_enabled', false);
        config()->set('services.retailpos.lead_notifications.lead_notification_email', 'sales@retailpos.test');

        $this->withHeader('X-RetailPOS-Lead-Token', 'test-lead-token')
            ->postJson('/api/public/leads', $this->leadPayload())
            ->assertOk();

        $this->assertDatabaseMissing('notification_deliveries', [
            'event_key' => 'crm.lead.created',
            'channel' => 'email',
        ]);
        Queue::assertNotPushed(SendNotificationDeliveryJob::class);
    }

    public function test_notification_delivery_failure_does_not_prevent_public_lead_creation(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $this->configurePublicIntake($administrator);
        $this->app->bind(DatabaseNotificationChannel::class, fn (): NotificationChannel => new class implements NotificationChannel
        {
            public function send(User $recipient, DomainEvent $event, array $message, NotificationDelivery $delivery): NotificationDelivery
            {
                throw new RuntimeException('Notification provider unavailable.');
            }
        });

        $this->withHeader('X-RetailPOS-Lead-Token', 'test-lead-token')
            ->postJson('/api/public/leads', $this->leadPayload())
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Lead received successfully.']);

        $this->assertDatabaseCount('crm_leads', 1);
        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'crm.lead.created',
            'channel' => 'database',
            'status' => 'failed',
        ]);
    }

    public function test_notification_read_routes_mark_owned_notifications_read_and_redirect_to_the_lead(): void
    {
        $manager = $this->user(UserRole::Manager);
        $notification = $this->databaseNotification($manager, route('crm.leads.index'));

        $this->actingAs($manager)
            ->post("/notifications/{$notification->id}/read", ['redirect_to' => route('crm.leads.index')])
            ->assertRedirect(route('crm.leads.index'));

        $this->assertNotNull($notification->refresh()->read_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'notification.marked_read']);

        $this->actingAs($manager)
            ->post('/notifications/read-all')
            ->assertRedirect();
    }

    public function test_notification_settings_override_defaults_and_can_disable_public_lead_delivery(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $sales = $this->user(UserRole::Sales, $administrator->company, $administrator->branch);

        $this->actingAs($administrator)
            ->put('/settings/notifications', [
                'low_stock_alerts' => true,
                'daily_sales_digest' => true,
                'lead_alerts' => true,
                'lead_notifications_enabled' => false,
                'lead_email_notifications_enabled' => false,
                'lead_notification_email' => 'sales@retailpos.test',
                'notify_admins_on_new_lead' => true,
                'notify_sales_on_new_lead' => true,
                'followup_reminders_enabled' => true,
            ])
            ->assertRedirect();

        $settings = app(LeadNotificationSettings::class)->forCompany($administrator->company_id);
        $this->assertFalse($settings['lead_notifications_enabled']);
        $this->assertSame('sales@retailpos.test', $settings['lead_notification_email']);

        $this->configurePublicIntake($sales);

        $this->withHeader('X-RetailPOS-Lead-Token', 'test-lead-token')
            ->postJson('/api/public/leads', $this->leadPayload())
            ->assertOk();

        $this->assertSame(0, NotificationDelivery::query()->where('event_key', 'crm.lead.created')->count());
    }

    public function test_lead_follow_up_reminder_command_notifies_assignee_and_administrators_once(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $sales = $this->user(UserRole::Sales, $administrator->company, $administrator->branch);
        $source = CrmLeadSource::create([
            'company_id' => $administrator->company_id,
            'name' => 'Website Contact',
            'slug' => 'website-contact',
            'is_active' => true,
        ]);
        $active = $this->leadStatus($administrator, 'Follow Up', LeadStageType::FollowUp);
        $won = $this->leadStatus($administrator, 'Won', LeadStageType::Won, true);
        $spam = $this->leadStatus($administrator, 'Spam', LeadStageType::Spam);

        $dueLead = $this->lead($administrator, $source, $active, ['assigned_user_id' => $sales->id]);
        $this->lead($administrator, $source, $won, ['title' => 'Won lead']);
        $this->lead($administrator, $source, $spam, ['title' => 'Spam lead']);

        $this->artisan('retailpos:lead-followup-reminders')->assertSuccessful();
        $this->artisan('retailpos:lead-followup-reminders')->assertSuccessful();

        $this->assertSame(1, DomainEventLog::query()
            ->where('correlation_id', 'crm.lead.follow_up.due:'.$dueLead->id.':'.$dueLead->next_follow_up_at->timestamp)
            ->count());
        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'crm.follow_up.due',
            'user_id' => $administrator->id,
            'channel' => 'database',
        ]);
        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'crm.follow_up.due',
            'user_id' => $sales->id,
            'channel' => 'database',
        ]);
        $this->assertSame(1, NotificationDelivery::query()->where('event_key', 'crm.follow_up.due')->where('channel', 'database')->where('user_id', $sales->id)->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function leadPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Asha Mehta',
            'company_name' => 'Acme Retail',
            'email' => 'asha@example.test',
            'phone' => '+91 90000 11111',
            'source' => 'contact',
        ], $overrides);
    }

    private function configurePublicIntake(User $user): void
    {
        config()->set('services.retailpos.public_lead_token', 'test-lead-token');
        config()->set('services.retailpos.public_lead_company_id', $user->company_id);
        config()->set('services.retailpos.public_lead_assignee_id', $user->id);
    }

    private function databaseNotification(User $user, string $actionUrl): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) str()->uuid(),
            'type' => PlatformNotification::class,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => [
                'title' => 'New lead received',
                'message' => 'A lead requires review.',
                'event_key' => 'crm.lead.created',
                'action_url' => $actionUrl,
            ],
            'read_at' => null,
        ]);
    }

    private function leadStatus(User $user, string $name, LeadStageType $stage, bool $isWon = false): CrmLeadStatus
    {
        return CrmLeadStatus::create([
            'company_id' => $user->company_id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'stage_type' => $stage->value,
            'is_won' => $isWon,
            'is_lost' => $stage === LeadStageType::Lost,
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function lead(User $user, CrmLeadSource $source, CrmLeadStatus $status, array $overrides = []): CrmLead
    {
        return CrmLead::create(array_merge([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'source_id' => $source->id,
            'status_id' => $status->id,
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Due follow-up lead',
            'contact_name' => 'Asha Mehta',
            'priority' => LeadPriority::High->value,
            'next_follow_up_at' => now()->subMinute(),
        ], $overrides));
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

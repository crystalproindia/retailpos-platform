<?php

namespace Tests\Feature;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Events\Domain\Crm\LeadAssigned;
use App\Jobs\Notifications\SendWebhookDeliveryJob;
use App\Models\Branch;
use App\Models\Cms\CmsPage;
use App\Models\Company;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\DomainEventLog;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Notifications\PlatformNotification;
use App\Services\Events\DomainEventDispatcher;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationCenterFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_center_is_registered_and_role_filtered(): void
    {
        $registry = new ModuleRegistry;

        $notifications = $registry->find('notifications');
        $adminSidebar = $registry->sidebar(UserRole::Administrator);
        $salesSidebar = $registry->sidebar(UserRole::Sales);
        $staffSidebar = $registry->sidebar(UserRole::Staff);
        $adminNotifications = $adminSidebar->firstWhere('id', 'notifications');

        $this->assertSame('notifications.index', $notifications->route);
        $this->assertSame('System & Operations', $notifications->category);
        $this->assertContains('notification-inbox', collect($adminNotifications->children)->pluck('id'));
        $this->assertContains('notification-webhooks', collect($adminNotifications->children)->pluck('id'));
        $this->assertTrue($adminSidebar->contains('id', 'notifications'));
        $this->assertTrue($salesSidebar->contains('id', 'notifications'));
        $this->assertFalse($staffSidebar->contains('id', 'notifications'));
    }

    public function test_notification_routes_respect_permissions(): void
    {
        $admin = $this->user(UserRole::Administrator);
        $manager = $this->user(UserRole::Manager, $admin->company, $admin->branch);
        $sales = $this->user(UserRole::Sales, $admin->company, $admin->branch);
        $staff = $this->user(UserRole::Staff, $admin->company, $admin->branch);

        $this->actingAs($admin)->get('/notifications')->assertOk();
        $this->actingAs($manager)->get('/notifications/events')->assertOk();
        $this->actingAs($sales)->get('/notifications/preferences')->assertOk();
        $this->actingAs($sales)->get('/notifications/events')->assertForbidden();
        $this->actingAs($manager)->get('/notifications/templates')->assertForbidden();
        $this->actingAs($staff)->get('/notifications')->assertForbidden();
    }

    public function test_inbox_actions_are_scoped_to_notification_owner(): void
    {
        $manager = $this->user(UserRole::Manager);
        $other = $this->user(UserRole::Manager);
        $notification = $this->databaseNotification($manager, 'Lead assigned', 'You have a new lead.');

        $this->actingAs($manager)
            ->get('/notifications?status=unread')
            ->assertOk()
            ->assertSee('Lead assigned');

        $this->actingAs($other)
            ->post("/notifications/inbox/{$notification->id}/read")
            ->assertNotFound();

        $this->actingAs($manager)
            ->post("/notifications/inbox/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->refresh()->read_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'notification.marked_read']);

        $this->actingAs($manager)
            ->post("/notifications/inbox/{$notification->id}/unread")
            ->assertRedirect();

        $this->assertNull($notification->refresh()->read_at);

        $this->actingAs($manager)
            ->delete("/notifications/inbox/{$notification->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_preferences_update_reset_and_future_channels_are_disabled(): void
    {
        $sales = $this->user(UserRole::Sales);

        $this->actingAs($sales)
            ->put('/notifications/preferences', [
                'preferences' => [
                    'crm.lead.assigned' => [
                        'database_enabled' => '1',
                        'email_enabled' => '1',
                        'whatsapp_enabled' => '1',
                        'quiet_hours_enabled' => '1',
                        'quiet_hours_start' => '20:00',
                        'quiet_hours_end' => '08:00',
                        'timezone' => 'Asia/Kolkata',
                    ],
                ],
            ])
            ->assertRedirect();

        $preference = NotificationPreference::query()
            ->where('user_id', $sales->id)
            ->where('event_key', 'crm.lead.assigned')
            ->firstOrFail();

        $this->assertTrue($preference->database_enabled);
        $this->assertTrue($preference->email_enabled);
        $this->assertFalse($preference->whatsapp_enabled);
        $this->assertSame('Asia/Kolkata', $preference->timezone);

        $this->actingAs($sales)
            ->post('/notifications/preferences/reset')
            ->assertRedirect();

        $this->assertDatabaseMissing('notification_preferences', ['id' => $preference->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'notification.preferences.updated']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'notification.preferences.reset']);
    }

    public function test_crm_lead_creation_emits_domain_event_and_database_notifications(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $fixtures = $this->crmFixtures($manager);

        $this->actingAs($manager)
            ->post('/crm/leads', $this->leadPayload($fixtures, [
                'title' => 'Notification Event Lead',
                'assigned_user_id' => $sales->id,
            ]))
            ->assertRedirect();

        $lead = CrmLead::query()->where('title', 'Notification Event Lead')->firstOrFail();

        $this->assertDatabaseHas('domain_event_logs', [
            'event_key' => 'crm.lead.created',
            'aggregate_type' => CrmLead::class,
            'aggregate_id' => $lead->id,
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'crm.lead.created',
            'channel' => 'database',
            'status' => 'delivered',
            'user_id' => $sales->id,
        ]);
        $this->assertTrue($sales->notifications()->where('data->event_key', 'crm.lead.created')->exists());
    }

    public function test_cms_publish_and_settings_update_emit_domain_events(): void
    {
        $manager = $this->user(UserRole::Manager);
        $page = CmsPage::create([
            'company_id' => $manager->company_id,
            'author_user_id' => $manager->id,
            'slug' => 'notification-page',
            'title' => 'Notification Page',
            'status' => CmsPage::STATUS_DRAFT,
        ]);

        $this->actingAs($manager)
            ->post("/cms/pages/{$page->id}/publish")
            ->assertRedirect();

        $this->assertDatabaseHas('domain_event_logs', [
            'event_key' => 'cms.page.published',
            'aggregate_type' => CmsPage::class,
            'aggregate_id' => $page->id,
            'status' => 'processed',
        ]);

        $this->actingAs($manager)
            ->put('/settings/general', [
                'timezone' => 'Asia/Kolkata',
                'currency' => 'INR',
                'date_format' => 'd M Y',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('domain_event_logs', [
            'event_key' => 'system.settings.updated',
            'aggregate_type' => 'settings',
            'status' => 'processed',
        ]);
    }

    public function test_disabled_future_channel_records_unsupported_delivery(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);

        NotificationPreference::create([
            'company_id' => $sales->company_id,
            'user_id' => $sales->id,
            'event_key' => 'crm.lead.assigned',
            'database_enabled' => false,
            'email_enabled' => false,
            'whatsapp_enabled' => true,
        ]);

        app(DomainEventDispatcher::class)->dispatch(new LeadAssigned(
            companyId: $sales->company_id,
            actorId: $manager->id,
            aggregateType: CrmLead::class,
            aggregateId: 123,
            payload: [
                'lead_id' => 123,
                'lead_title' => 'Unsupported Channel Lead',
                'business_name' => 'Future Channel Retail',
                'assigned_user_id' => $sales->id,
            ],
        ));

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $sales->id,
            'event_key' => 'crm.lead.assigned',
            'channel' => 'whatsapp',
            'status' => 'unsupported',
        ]);
    }

    public function test_webhook_endpoints_validate_private_urls_sign_and_queue_deliveries(): void
    {
        Queue::fake();

        $admin = $this->user(UserRole::Administrator);
        $fixtures = $this->crmFixtures($admin);

        $this->actingAs($admin)
            ->from('/notifications/webhooks')
            ->post('/notifications/webhooks', [
                'name' => 'Private URL',
                'url' => 'https://127.0.0.1/hook',
                'subscribed_events' => ['crm.lead.created'],
            ])
            ->assertRedirect('/notifications/webhooks')
            ->assertSessionHasErrors('url');

        $this->actingAs($admin)
            ->post('/notifications/webhooks', [
                'name' => 'CRM Automation',
                'url' => 'https://hooks.example.com/retailpos',
                'subscribed_events' => ['crm.lead.created'],
                'is_active' => '1',
            ])
            ->assertRedirect();

        $endpoint = WebhookEndpoint::query()->where('name', 'CRM Automation')->firstOrFail();
        $oldSecret = $endpoint->secret;

        $this->actingAs($admin)
            ->post("/notifications/webhooks/{$endpoint->id}/rotate-secret")
            ->assertRedirect();

        $this->assertNotSame($oldSecret, $endpoint->refresh()->secret);

        $this->actingAs($admin)
            ->post('/crm/leads', $this->leadPayload($fixtures, [
                'title' => 'Webhook Lead',
                'assigned_user_id' => $admin->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('webhook_deliveries', [
            'company_id' => $admin->company_id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_key' => 'crm.lead.created',
            'status' => 'queued',
        ]);
        Queue::assertPushed(SendWebhookDeliveryJob::class);
    }

    public function test_follow_up_reminder_commands_are_idempotent(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->crmFixtures($manager);
        $lead = $this->lead($manager, $fixtures);

        $activity = CrmActivity::create([
            'company_id' => $manager->company_id,
            'crm_lead_id' => $lead->id,
            'assigned_user_id' => $manager->id,
            'created_by' => $manager->id,
            'type' => ActivityType::FollowUp->value,
            'subject' => 'Due reminder test',
            'scheduled_at' => now()->addMinutes(5),
            'priority' => LeadPriority::High->value,
        ]);

        Artisan::call('notifications:dispatch-followup-due');
        Artisan::call('notifications:dispatch-followup-due');

        $this->assertSame(1, DomainEventLog::query()
            ->where('correlation_id', 'crm.follow_up.due:'.$activity->id)
            ->count());
        $this->assertDatabaseHas('notification_deliveries', [
            'event_key' => 'crm.follow_up.due',
            'user_id' => $manager->id,
            'channel' => 'database',
        ]);
    }

    public function test_seed_data_creates_notification_foundation_records(): void
    {
        $this->seed();

        $this->assertDatabaseHas('notification_templates', [
            'event_key' => 'crm.lead.assigned',
            'channel' => 'email',
            'is_system' => true,
        ]);
        $this->assertDatabaseHas('notification_preferences', [
            'event_key' => 'crm.follow_up.due',
            'email_enabled' => true,
        ]);
        $this->assertDatabaseHas('domain_event_logs', [
            'correlation_id' => 'seed:system.settings.updated',
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas('webhook_endpoints', [
            'name' => 'Demo automation endpoint',
            'is_active' => false,
        ]);
    }

    private function databaseNotification(User $user, string $title, string $message): DatabaseNotification
    {
        return DatabaseNotification::create([
            'id' => (string) str()->uuid(),
            'type' => PlatformNotification::class,
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => [
                'title' => $title,
                'message' => $message,
                'event_key' => 'crm.lead.assigned',
                'severity' => 'info',
            ],
            'read_at' => null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function crmFixtures(User $user): array
    {
        $source = CrmLeadSource::create([
            'company_id' => $user->company_id,
            'name' => 'Website Demo',
            'slug' => 'website-demo',
            'is_active' => true,
        ]);

        $new = $this->crmStatus($user, 'New', LeadStageType::New, 1);
        $qualified = $this->crmStatus($user, 'Qualified', LeadStageType::Qualified, 2);

        return compact('source', 'new', 'qualified');
    }

    private function crmStatus(User $user, string $name, LeadStageType $stage, int $sortOrder): CrmLeadStatus
    {
        return CrmLeadStatus::create([
            'company_id' => $user->company_id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'stage_type' => $stage->value,
            'probability' => 25,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function lead(User $user, array $fixtures, array $overrides = []): CrmLead
    {
        return CrmLead::create(array_merge([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'source_id' => $fixtures['source']->id,
            'status_id' => $fixtures['new']->id,
            'assigned_user_id' => $user->id,
            'created_by' => $user->id,
            'title' => 'Notification CRM Lead',
            'business_name' => 'Notification Retail',
            'contact_name' => 'Notification Contact',
            'email' => 'notify@example.test',
            'phone' => '+91 90000 11111',
            'expected_value' => 125000,
            'currency' => 'INR',
            'priority' => LeadPriority::Medium->value,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function leadPayload(array $fixtures, array $overrides = []): array
    {
        return array_merge([
            'source_id' => $fixtures['source']->id,
            'status_id' => $fixtures['new']->id,
            'title' => 'Notification CRM Lead',
            'business_name' => 'Notification Retail',
            'contact_name' => 'Notification Contact',
            'email' => 'notify@example.test',
            'phone' => '+91 90000 11111',
            'expected_value' => 125000,
            'currency' => 'INR',
            'priority' => LeadPriority::Medium->value,
            'lead_score' => 70,
            'next_follow_up_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ], $overrides);
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

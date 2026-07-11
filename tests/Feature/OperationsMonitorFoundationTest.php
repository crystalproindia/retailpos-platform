<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\DomainEventLog;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsMonitorFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_module_is_registered_and_role_filtered(): void
    {
        $registry = new ModuleRegistry;
        $operations = $registry->find('operations');
        $adminSidebar = $registry->sidebar(UserRole::Administrator);
        $managerSidebar = $registry->sidebar(UserRole::Manager);
        $salesSidebar = $registry->sidebar(UserRole::Sales);
        $adminOperations = $adminSidebar->firstWhere('id', 'operations');

        $this->assertSame('operations.dashboard', $operations->route);
        $this->assertSame('System & Operations', $operations->category);
        $this->assertTrue($adminSidebar->contains('id', 'operations'));
        $this->assertTrue($managerSidebar->contains('id', 'operations'));
        $this->assertFalse($salesSidebar->contains('id', 'operations'));
        $this->assertContains('operations-health', collect($adminOperations->children)->pluck('id'));
        $this->assertContains('operations-failed-jobs', collect($adminOperations->children)->pluck('id'));
    }

    public function test_operations_access_is_limited_to_administrator_and_manager(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $manager = $this->user(UserRole::Manager, $administrator->company, $administrator->branch);
        $sales = $this->user(UserRole::Sales, $administrator->company, $administrator->branch);
        $staff = $this->user(UserRole::Staff, $administrator->company, $administrator->branch);

        $this->actingAs($administrator)->get('/operations')->assertOk();
        $this->actingAs($manager)->get('/operations')->assertOk();
        $this->actingAs($sales)->get('/operations')->assertForbidden();
        $this->actingAs($staff)->get('/operations')->assertForbidden();
    }

    public function test_operations_dashboard_loads_with_notification_webhook_and_event_failure_counts(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $endpoint = WebhookEndpoint::create([
            'company_id' => $administrator->company_id,
            'name' => 'Operations Test Endpoint',
            'url' => 'https://hooks.example.com/test',
            'secret' => 'whsec_test_secret',
            'subscribed_events' => ['crm.lead.created'],
            'is_active' => true,
        ]);
        NotificationDelivery::create([
            'company_id' => $administrator->company_id,
            'user_id' => $administrator->id,
            'event_key' => 'crm.lead.created',
            'channel' => 'email',
            'recipient' => $administrator->email,
            'status' => 'failed',
        ]);
        WebhookDelivery::create([
            'company_id' => $administrator->company_id,
            'webhook_endpoint_id' => $endpoint->id,
            'event_key' => 'crm.lead.created',
            'payload' => ['demo' => true],
            'status' => 'failed',
        ]);
        DomainEventLog::create([
            'company_id' => $administrator->company_id,
            'user_id' => $administrator->id,
            'event_key' => 'crm.lead.created',
            'event_class' => 'test',
            'aggregate_type' => 'test',
            'aggregate_id' => 1,
            'correlation_id' => (string) Str::uuid(),
            'payload' => ['demo' => true],
            'occurred_at' => now(),
            'status' => 'failed',
        ]);

        $this->actingAs($administrator)
            ->get('/operations')
            ->assertOk()
            ->assertSee('Notification failures')
            ->assertSee('Webhook failures')
            ->assertSee('Event failures');
    }

    public function test_health_check_command_records_database_cache_and_storage_checks(): void
    {
        $exitCode = Artisan::call('operations:health-check');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('system_health_checks', ['key' => 'database']);
        $this->assertDatabaseHas('system_health_checks', ['key' => 'cache']);
        $this->assertDatabaseHas('system_health_checks', ['key' => 'storage']);
        $this->assertDatabaseHas('scheduled_task_runs', ['command' => 'operations:health-check']);
    }

    public function test_queue_snapshot_command_and_schedule_list_are_compatible(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp,
            'created_at' => now()->timestamp,
        ]);

        $this->assertSame(0, Artisan::call('operations:capture-queue-snapshot'));
        $this->assertDatabaseHas('queue_job_snapshots', [
            'queue' => 'default',
            'pending_count' => 1,
        ]);

        $this->artisan('schedule:list')
            ->assertExitCode(0)
            ->expectsOutputToContain('operations:health-check')
            ->expectsOutputToContain('operations:capture-queue-snapshot');
    }

    public function test_failed_jobs_are_listed_with_redacted_payloads_and_manager_cannot_retry_or_delete(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $manager = $this->user(UserRole::Manager, $administrator->company, $administrator->branch);
        $failedJobId = $this->failedJob('default', 'password=super-secret token=hidden-token');

        $this->actingAs($manager)
            ->get('/operations/failed-jobs')
            ->assertOk()
            ->assertSee('DemoFailedJob')
            ->assertDontSee('super-secret')
            ->assertDontSee('hidden-token');

        $this->actingAs($manager)
            ->post("/operations/failed-jobs/{$failedJobId}/retry")
            ->assertForbidden();

        $this->actingAs($manager)
            ->delete("/operations/failed-jobs/{$failedJobId}")
            ->assertForbidden();
    }

    public function test_administrator_can_retry_delete_and_bulk_manage_failed_jobs(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $retryId = $this->failedJob('default', 'Runtime exception');
        $deleteId = $this->failedJob('default', 'Delete exception');
        $bulkRetryId = $this->failedJob('emails', 'Bulk retry exception');
        $bulkDeleteId = $this->failedJob('emails', 'Bulk delete exception');

        $this->actingAs($administrator)
            ->post("/operations/failed-jobs/{$retryId}/retry")
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['id' => $retryId]);
        $this->assertDatabaseHas('jobs', ['queue' => 'default']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'operations.failed_job.retried']);

        $this->actingAs($administrator)
            ->delete("/operations/failed-jobs/{$deleteId}")
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['id' => $deleteId]);

        $this->actingAs($administrator)
            ->post('/operations/failed-jobs/bulk-retry', ['ids' => [$bulkRetryId]])
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['id' => $bulkRetryId]);

        $this->actingAs($administrator)
            ->delete('/operations/failed-jobs/bulk-delete', ['ids' => [$bulkDeleteId]])
            ->assertRedirect();

        $this->assertDatabaseMissing('failed_jobs', ['id' => $bulkDeleteId]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'operations.failed_jobs.bulk_retried']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'operations.failed_jobs.bulk_deleted']);
    }

    public function test_operations_pages_load_and_application_info_does_not_expose_secrets(): void
    {
        $administrator = $this->user(UserRole::Administrator);

        $this->actingAs($administrator)->get('/operations/health')->assertOk();
        $this->actingAs($administrator)->get('/operations/queue')->assertOk();
        $this->actingAs($administrator)->get('/operations/schedule')->assertOk();
        $this->actingAs($administrator)
            ->get('/operations/application')
            ->assertOk()
            ->assertSee('Application Info')
            ->assertDontSee(config('app.key') ?: 'base64:');
    }

    public function test_database_seeder_creates_operations_demo_records(): void
    {
        $this->seed();

        $this->assertDatabaseHas('system_health_checks', [
            'key' => 'demo_app_boot',
            'category' => 'Demo',
        ]);
        $this->assertDatabaseHas('queue_job_snapshots', [
            'queue' => 'demo-default',
        ]);
    }

    private function failedJob(string $queue, string $exception): int
    {
        return (int) DB::table('failed_jobs')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => $queue,
            'payload' => json_encode([
                'uuid' => (string) Str::uuid(),
                'displayName' => 'DemoFailedJob',
                'job' => 'Illuminate\Queue\CallQueuedHandler@call',
                'maxTries' => null,
                'timeout' => null,
                'data' => [
                    'commandName' => 'DemoFailedJob',
                    'password' => 'super-secret',
                    'token' => 'hidden-token',
                ],
            ]),
            'exception' => $exception,
            'failed_at' => now(),
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

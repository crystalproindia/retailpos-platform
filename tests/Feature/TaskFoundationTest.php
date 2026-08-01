<?php

namespace Tests\Feature;

use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\Tasks\TaskStatus;
use App\Enums\Tasks\TaskType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Tasks\Task;
use App\Models\Tasks\TaskReminderDelivery;
use App\Models\Tasks\TaskRuleSetting;
use App\Models\User;
use App\Notifications\PlatformNotification;
use App\Services\Tasks\TaskReminderService;
use App\Services\Tasks\TaskRuleService;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function testPersonalTaskPrivacyIsPreservedAcrossDirectUrlsDashboardsAndExports(): void
    {
        [$company, $outlet, $owner] = $this->tenantUser(UserRole::Staff);
        $administrator = $this->user(UserRole::Administrator, $company, $outlet);
        $peer = $this->user(UserRole::Staff, $company, $outlet);

        $this->actingAs($owner)->post('/tasks', [
            'task_type' => 'personal',
            'title' => 'Private medical appointment',
            'description' => 'Sensitive personal note',
            'priority' => 'normal',
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'outlet_id' => $outlet->id,
            'assigned_user_id' => $administrator->id,
        ])->assertRedirect();

        $task = Task::query()->firstOrFail();
        $this->assertSame(TaskType::Personal, $task->task_type);
        $this->assertSame($owner->id, $task->owner_user_id);
        $this->assertSame($owner->id, $task->assigned_user_id);
        $this->assertNull($task->outlet_id);
        $this->assertDatabaseMissing('audit_logs', ['description' => 'Private medical appointment']);
        $this->assertDatabaseMissing('audit_logs', ['description' => 'Sensitive personal note']);

        $this->actingAs($owner)->get("/tasks/{$task->id}")->assertOk()->assertSee('Private medical appointment');
        $this->actingAs($peer)->get("/tasks/{$task->id}")->assertNotFound();
        $this->actingAs($administrator)->get("/tasks/{$task->id}")->assertNotFound();
        $this->actingAs($administrator)->get('/tasks/team')->assertOk()->assertDontSee('Private medical appointment');
        $this->actingAs($administrator)->get('/tasks/export')->assertOk()->assertDontSee('Private medical appointment');
        $this->actingAs($administrator)->get('/workforce')->assertOk()->assertDontSee('Private medical appointment');
        $this->actingAs($administrator)->get('/dashboard')->assertOk()->assertDontSee('Private medical appointment');
    }

    public function testWorkTaskAuthorizationIsOutletScopedForManagersAndCompanyWideForAdministrators(): void
    {
        [$company, $primary, $manager] = $this->tenantUser(UserRole::Manager);
        $secondary = Branch::factory()->create(['company_id' => $company->id]);
        $secondManager = $this->user(UserRole::Manager, $company, $secondary);
        $administrator = $this->user(UserRole::Administrator, $company, $primary);

        $this->actingAs($manager)->post('/tasks', [
            'task_type' => 'work', 'title' => 'Secondary outlet task', 'priority' => 'high',
            'outlet_id' => $secondary->id, 'assigned_user_id' => $secondManager->id,
        ])->assertSessionHasErrors('outlet_id');

        $task = Task::create([
            'company_id' => $company->id, 'outlet_id' => $secondary->id, 'owner_user_id' => $administrator->id,
            'assigned_user_id' => $secondManager->id, 'created_by_user_id' => $administrator->id,
            'task_type' => 'work', 'source_type' => 'manual', 'title' => 'Secondary outlet stock review',
            'priority' => 'high', 'status' => 'todo', 'due_at' => now()->addHour(),
        ]);

        $this->actingAs($manager)->get("/tasks/{$task->id}")->assertNotFound();
        $this->actingAs($secondManager)->get("/tasks/{$task->id}")->assertOk()->assertSee('Secondary outlet stock review');
        $this->actingAs($administrator)->get("/tasks/{$task->id}")->assertOk()->assertSee('Secondary outlet stock review');
    }

    public function testLeadFollowUpTaskUsesTheSharedFlowAndPreservesLeadHistoryWithoutChangingStatus(): void
    {
        [$company, $outlet, $manager] = $this->tenantUser(UserRole::Manager);
        $lead = $this->lead($manager);
        $originalStatus = $lead->status_id;

        $this->actingAs($manager)->post('/tasks', [
            'task_type' => 'work', 'title' => 'Call lead about rollout', 'priority' => 'high',
            'due_at' => now()->addHour()->format('Y-m-d H:i:s'), 'outlet_id' => $outlet->id,
            'assigned_user_id' => $manager->id, 'related_type' => 'lead', 'related_id' => $lead->id,
        ])->assertRedirect();

        $task = Task::query()->firstOrFail();
        $this->actingAs($manager)->get('/tasks?search=Retail%20rollout')->assertOk()->assertSee('Call lead about rollout');
        $this->actingAs($manager)->get('/tasks?search='.$manager->name)->assertOk()->assertSee('Call lead about rollout');
        $this->actingAs($manager)->get("/crm/leads/{$lead->id}")->assertOk()->assertSee('Lead tasks')->assertSee('Call lead about rollout');
        $this->actingAs($manager)->post("/tasks/{$task->id}/transition", [
            'status' => 'completed', 'completion_note' => 'Discovery call held.',
            'next_follow_up_title' => 'Send proposal summary', 'next_follow_up_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertSame(TaskStatus::Completed, $task->refresh()->status);
        $this->assertSame($originalStatus, $lead->refresh()->status_id);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => 'Task completed']);
        $this->assertDatabaseHas('tasks', ['company_id' => $company->id, 'title' => 'Send proposal summary', 'related_id' => $lead->id]);
    }

    public function testRecurringTaskCreatesOneNextOccurrenceAndKeepsHistory(): void
    {
        [, , $staff] = $this->tenantUser(UserRole::Staff);

        $this->actingAs($staff)->post('/tasks', [
            'task_type' => 'personal', 'title' => 'Weekly planning', 'priority' => 'normal',
            'due_at' => now()->addDay()->format('Y-m-d H:i:s'), 'recurrence_type' => 'weekly',
        ])->assertRedirect();

        $task = Task::query()->firstOrFail();
        $this->actingAs($staff)->post("/tasks/{$task->id}/transition", ['status' => 'completed'])->assertRedirect();
        $this->actingAs($staff)->post("/tasks/{$task->id}/transition", ['status' => 'completed'])->assertRedirect();

        $this->assertSame(2, Task::query()->where('recurrence_series_id', $task->recurrence_series_id)->count());
        $next = Task::query()->where('recurrence_parent_id', $task->id)->firstOrFail();
        $this->assertSame($task->due_at->copy()->addWeek()->format('Y-m-d H:i'), $next->due_at->format('Y-m-d H:i'));
    }

    public function testAutomaticTaskRuleIsIdempotentAndDisabledRulesDoNotCreateWork(): void
    {
        [$company, , $manager] = $this->tenantUser(UserRole::Manager);
        $lead = $this->lead($manager, ['next_follow_up_at' => now()->subHour()]);
        TaskRuleSetting::create(['company_id' => $company->id, 'rule_key' => 'lead_follow_up_due', 'is_enabled' => true, 'threshold_hours' => 1, 'updated_by' => $manager->id]);

        $first = app(TaskRuleService::class)->generate($company->id);
        $second = app(TaskRuleService::class)->generate($company->id);

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertDatabaseHas('tasks', ['company_id' => $company->id, 'related_id' => $lead->id, 'system_rule_key' => 'lead_follow_up_due', 'source_type' => 'system_rule']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'event' => 'tasks.work.rule_skipped']);
        $this->assertSame(1, Task::query()->where('company_id', $company->id)->count());
    }

    public function testTaskReminderIsOwnerOnlyForPersonalTasksAndDeliveryIsIdempotent(): void
    {
        [$company, , $owner] = $this->tenantUser(UserRole::Staff);
        $task = Task::create([
            'company_id' => $company->id, 'owner_user_id' => $owner->id, 'assigned_user_id' => $owner->id,
            'created_by_user_id' => $owner->id, 'task_type' => 'personal', 'source_type' => 'manual',
            'title' => 'Private reminder', 'priority' => 'normal', 'status' => 'todo', 'due_at' => now()->subMinute(),
        ]);

        Notification::fake();
        $first = app(TaskReminderService::class)->deliverDueReminders($company->id);
        $second = app(TaskReminderService::class)->deliverDueReminders($company->id);

        $this->assertSame(1, $first['overdue']);
        $this->assertSame(1, $second['overdue']);
        Notification::assertSentTo($owner, PlatformNotification::class);
        $this->assertSame(1, TaskReminderDelivery::query()->where('task_id', $task->id)->where('channel', 'database')->where('kind', 'overdue')->count());
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'event' => 'tasks.personal.reminder_sent', 'description' => 'Personal task reminder activity recorded']);
        $this->assertDatabaseMissing('audit_logs', ['description' => 'Private reminder']);
    }

    public function testTaskBrowserNavigationAndRuleAuthorizationFollowThePermissionMatrix(): void
    {
        [$company, $outlet, $administrator] = $this->tenantUser(UserRole::Administrator);
        $manager = $this->user(UserRole::Manager, $company, $outlet);
        $staff = $this->user(UserRole::Staff, $company, $outlet);
        $registry = app(ModuleRegistry::class);

        $this->assertTrue($registry->sidebarForUser($staff)->contains('id', 'tasks'));
        $this->assertFalse(collect($registry->sidebarForUser($staff)->firstWhere('id', 'tasks')->children)->contains('id', 'tasks-team'));
        $this->assertTrue(collect($registry->sidebarForUser($manager)->firstWhere('id', 'tasks')->children)->contains('id', 'tasks-team'));
        $this->actingAs($staff)->get('/tasks/settings/rules')->assertForbidden();
        $this->actingAs($administrator)->get('/tasks/settings/rules')->assertOk()->assertSee('Task rules');
    }

    public function testTaskExportEscapesFormulaPrefixesAndExcludesPersonalTasks(): void
    {
        [$company, $outlet, $administrator] = $this->tenantUser(UserRole::Administrator);
        Task::create(['company_id' => $company->id, 'outlet_id' => $outlet->id, 'owner_user_id' => $administrator->id, 'assigned_user_id' => $administrator->id, 'created_by_user_id' => $administrator->id, 'task_type' => 'work', 'source_type' => 'manual', 'title' => '=HYPERLINK("bad")', 'priority' => 'normal', 'status' => 'todo']);
        Task::create(['company_id' => $company->id, 'owner_user_id' => $administrator->id, 'assigned_user_id' => $administrator->id, 'created_by_user_id' => $administrator->id, 'task_type' => 'personal', 'source_type' => 'manual', 'title' => 'Private export exclusion', 'priority' => 'normal', 'status' => 'todo']);

        $response = $this->actingAs($administrator)->get('/tasks/export');
        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK(\"\"bad\"\")", $csv);
        $this->assertStringNotContainsString('Private export exclusion', $csv);
    }

    public function testTaskFoundationCreatesPersonalAndWorkTasksAndRejectsInvalidLifecycleTransitions(): void
    {
        [$company, $outlet, $staff] = $this->tenantUser(UserRole::Staff);

        $this->actingAs($staff)->post('/tasks', [
            'task_type' => 'personal', 'title' => 'Prepare personal list', 'priority' => 'low',
        ])->assertRedirect();
        $this->actingAs($staff)->post('/tasks', [
            'task_type' => 'work', 'title' => 'Check receiving desk', 'priority' => 'normal',
            'outlet_id' => $outlet->id, 'assigned_user_id' => $staff->id,
        ])->assertRedirect();

        $workTask = Task::query()->where('task_type', TaskType::Work)->firstOrFail();
        $this->actingAs($staff)->post("/tasks/{$workTask->id}/transition", ['status' => 'in_progress'])->assertRedirect();
        $this->actingAs($staff)->post("/tasks/{$workTask->id}/transition", ['status' => 'completed'])->assertRedirect();
        $this->actingAs($staff)->post("/tasks/{$workTask->id}/transition", ['status' => 'waiting'])
            ->assertSessionHasErrors('status');
        $this->actingAs($staff)->post("/tasks/{$workTask->id}/transition", ['status' => 'todo'])->assertForbidden();

        $this->assertSame(2, Task::query()->where('company_id', $company->id)->count());
        $this->assertSame(TaskStatus::Completed, $workTask->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'event' => 'tasks.work.started']);
    }

    public function testTaskAssignmentRejectsCrossTenantOutletAndInactiveUserTargets(): void
    {
        [$company, $outlet, $manager] = $this->tenantUser(UserRole::Manager);
        $inactive = $this->user(UserRole::Staff, $company, $outlet);
        $staff = $this->user(UserRole::Staff, $company, $outlet);
        $inactive->update(['is_active' => false]);
        [, $otherOutlet, $otherTenantUser] = $this->tenantUser(UserRole::Staff);

        $this->actingAs($manager)->post('/tasks', [
            'task_type' => 'work', 'title' => 'Assign to inactive user', 'priority' => 'normal',
            'outlet_id' => $outlet->id, 'assigned_user_id' => $inactive->id,
        ])->assertSessionHasErrors('assigned_user_id');
        $this->actingAs($manager)->post('/tasks', [
            'task_type' => 'work', 'title' => 'Assign outside tenant', 'priority' => 'normal',
            'outlet_id' => $outlet->id, 'assigned_user_id' => $otherTenantUser->id,
        ])->assertNotFound();
        $this->actingAs($manager)->get('/tasks?outlet_id='.$otherOutlet->id)->assertForbidden();
        $this->actingAs($staff)->post('/tasks', [
            'task_type' => 'work', 'title' => 'Attempt unassigned work', 'priority' => 'normal',
            'outlet_id' => $outlet->id, 'assigned_user_id' => null,
        ])->assertForbidden();

        $this->assertSame(0, Task::query()->where('company_id', $company->id)->count());
    }

    public function testTaskRelatedRecordPrefillIsAllowListedTenantScopedAndNeverAvailableToPersonalTasks(): void
    {
        [$company, $outlet, $manager] = $this->tenantUser(UserRole::Manager);
        $lead = $this->lead($manager);
        [, , $otherManager] = $this->tenantUser(UserRole::Manager);
        $otherLead = $this->lead($otherManager);

        $this->actingAs($manager)->get('/tasks?create_related_type=lead&create_related_id='.$lead->id)
            ->assertOk()
            ->assertSee('Linked to Retail rollout');
        $this->actingAs($manager)->get('/tasks?create_related_type=lead&create_related_id='.$otherLead->id)->assertNotFound();
        $this->actingAs($manager)->post('/tasks', [
            'task_type' => 'personal', 'title' => 'Personal record link', 'priority' => 'normal',
            'related_type' => 'lead', 'related_id' => $lead->id,
        ])->assertSessionHasErrors('related_type');
        $this->actingAs($manager)->post('/tasks', [
            'task_type' => 'work', 'title' => 'Unsupported related record', 'priority' => 'normal',
            'outlet_id' => $outlet->id, 'related_type' => User::class, 'related_id' => $manager->id,
        ])->assertSessionHasErrors('related_type');
    }

    public function testTaskIdempotencyPreventsAFirstContactRuleFromFloodingLateLeadsAcrossSchedulerRuns(): void
    {
        Carbon::setTestNow(now()->startOfMinute());
        try {
            [$company, , $manager] = $this->tenantUser(UserRole::Manager);
            $lead = $this->lead($manager);
            $lead->forceFill(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)])->saveQuietly();
            TaskRuleSetting::create(['company_id' => $company->id, 'rule_key' => 'lead_first_contact_due', 'is_enabled' => true, 'threshold_hours' => 24, 'updated_by' => $manager->id]);

            $first = app(TaskRuleService::class)->generate($company->id);
            Carbon::setTestNow(now()->addMinutes(15));
            $second = app(TaskRuleService::class)->generate($company->id);

            $this->assertSame(1, $first['created']);
            $this->assertSame(0, $second['created']);
            $this->assertSame(1, Task::query()->where('company_id', $company->id)->where('related_id', $lead->id)->count());
            $this->assertDatabaseHas('tasks', ['idempotency_key' => 'task-rule:lead_first_contact_due:lead:'.$lead->id]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function testTaskDashboardUsesSharedAuthorizedMetricsWithoutLeakingPersonalContent(): void
    {
        [$company, $outlet, $staff] = $this->tenantUser(UserRole::Staff);
        Task::create(['company_id' => $company->id, 'outlet_id' => $outlet->id, 'owner_user_id' => $staff->id, 'assigned_user_id' => $staff->id, 'created_by_user_id' => $staff->id, 'task_type' => 'work', 'source_type' => 'manual', 'title' => 'Today work task', 'priority' => 'urgent', 'status' => 'todo', 'due_at' => now()->addHour()]);
        Task::create(['company_id' => $company->id, 'owner_user_id' => $staff->id, 'assigned_user_id' => $staff->id, 'created_by_user_id' => $staff->id, 'task_type' => 'personal', 'source_type' => 'manual', 'title' => 'Private dashboard title', 'priority' => 'normal', 'status' => 'todo', 'due_at' => now()->addHour()]);

        $this->actingAs($staff)->get('/dashboard')->assertOk()->assertSee('My task focus');
        $this->actingAs($staff)->get('/workforce/me')->assertOk()->assertSee('My tasks');
        $this->assertSame(1, app(\App\Repositories\Tasks\TaskRepository::class)->workMetrics($staff)['today']);
    }

    public function testTeamTaskDashboardExcludesPersonalTasksAndLimitsManagersToAuthorizedOutlets(): void
    {
        [$company, $outlet, $manager] = $this->tenantUser(UserRole::Manager);
        Task::create(['company_id' => $company->id, 'outlet_id' => $outlet->id, 'owner_user_id' => $manager->id, 'assigned_user_id' => $manager->id, 'created_by_user_id' => $manager->id, 'task_type' => 'work', 'source_type' => 'manual', 'title' => 'Team stock review', 'priority' => 'high', 'status' => 'todo', 'due_at' => now()->addHour()]);
        Task::create(['company_id' => $company->id, 'owner_user_id' => $manager->id, 'assigned_user_id' => $manager->id, 'created_by_user_id' => $manager->id, 'task_type' => 'personal', 'source_type' => 'manual', 'title' => 'Private manager task', 'priority' => 'normal', 'status' => 'todo', 'due_at' => now()->addHour()]);

        $this->actingAs($manager)->get('/tasks/team')->assertOk()->assertSee('Team stock review')->assertDontSee('Private manager task');
        $this->actingAs($manager)->post('/tasks', [
            'task_type' => 'work', 'title' => 'Unassigned opening checklist', 'priority' => 'normal',
            'outlet_id' => $outlet->id, 'assigned_user_id' => null,
        ])->assertRedirect();
        $this->actingAs($manager)->get('/workforce')->assertOk()->assertSee('Authorized work-task context')->assertDontSee('Private manager task');
        $this->actingAs($manager)->get('/dashboard')->assertOk()->assertSee('Authorized team workload')->assertSee('Unassigned work');
        $this->assertDatabaseHas('tasks', ['company_id' => $company->id, 'title' => 'Unassigned opening checklist', 'assigned_user_id' => null]);
    }

    public function testTaskAuditRecordsWorkEventsWithoutRecordingPersonalTaskContent(): void
    {
        [$company, $outlet, $manager] = $this->tenantUser(UserRole::Manager);

        $this->actingAs($manager)->post('/tasks', [
            'task_type' => 'work', 'title' => 'Audit stock review', 'priority' => 'normal',
            'outlet_id' => $outlet->id, 'assigned_user_id' => $manager->id,
        ])->assertRedirect();
        $this->actingAs($manager)->post('/tasks', [
            'task_type' => 'personal', 'title' => 'Private audit title', 'description' => 'Private audit note', 'priority' => 'normal',
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'event' => 'tasks.work.created', 'description' => 'Work task created']);
        $this->assertDatabaseHas('audit_logs', ['company_id' => $company->id, 'event' => 'tasks.personal.created', 'description' => 'Personal task activity recorded']);
        $this->assertDatabaseMissing('audit_logs', ['description' => 'Private audit title']);
        $this->assertDatabaseMissing('audit_logs', ['description' => 'Private audit note']);
    }

    /** @return array{0: Company, 1: Branch, 2: User} */
    private function tenantUser(UserRole $role): array
    {
        $company = Company::factory()->create();
        $outlet = Branch::factory()->create(['company_id' => $company->id, 'is_primary' => true]);
        $user = $this->user($role, $company, $outlet);

        return [$company, $outlet, $user];
    }

    private function user(UserRole $role, Company $company, Branch $outlet): User
    {
        $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $outlet->id, 'role' => $role, 'is_active' => true, 'account_status' => 'active']);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $outlet->id, 'user_id' => $user->id, 'is_default' => true, 'is_active' => true]);

        return $user;
    }

    /** @param array<string, mixed> $overrides */
    private function lead(User $user, array $overrides = []): CrmLead
    {
        $source = CrmLeadSource::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'website'], ['name' => 'Website', 'is_active' => true, 'is_default' => true, 'sort_order' => 1]);
        $status = CrmLeadStatus::firstOrCreate(['company_id' => $user->company_id, 'slug' => 'new'], ['name' => 'New', 'stage_type' => LeadStageType::New, 'is_active' => true, 'is_default' => true, 'sort_order' => 1]);

        return CrmLead::create(array_merge([
            'company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'source_id' => $source->id, 'status_id' => $status->id,
            'assigned_user_id' => $user->id, 'created_by' => $user->id, 'title' => 'Retail rollout', 'priority' => LeadPriority::Medium,
        ], $overrides));
    }
}

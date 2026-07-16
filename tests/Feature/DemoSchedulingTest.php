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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_schedule_a_demo_and_update_the_lead_lifecycle(): void
    {
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $fixtures = $this->fixtures($manager);
        $lead = $this->lead($manager, $fixtures);

        $this->actingAs($manager)
            ->post("/crm/leads/{$lead->id}/demos", $this->demoPayload($sales))
            ->assertRedirect("/crm/leads/{$lead->id}");

        $demo = DemoSchedule::query()->firstOrFail();

        $this->assertSame(DemoScheduleStatus::Scheduled, $demo->status);
        $this->assertSame($sales->id, $demo->assigned_to);
        $this->assertSame($fixtures['demo']->id, $lead->refresh()->status_id);
        $this->assertSame('2026-07-20 10:00:00', $lead->next_follow_up_at?->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('crm_activities', [
            'crm_lead_id' => $lead->id,
            'subject' => 'Demo scheduled for 20 Jul 2026, 10:00 AM.',
        ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.demo.scheduled', 'auditable_id' => $demo->id]);
        $this->assertTrue($sales->notifications()->where('data->event_key', 'crm.demo.scheduled')->exists());
        $this->assertTrue($manager->notifications()->where('data->event_key', 'crm.demo.scheduled')->exists());
    }

    public function test_unauthorized_staff_user_cannot_schedule_a_demo(): void
    {
        $manager = $this->user(UserRole::Manager);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);
        $fixtures = $this->fixtures($manager);
        $lead = $this->lead($manager, $fixtures);

        $this->actingAs($staff)
            ->post("/crm/leads/{$lead->id}/demos", $this->demoPayload($manager))
            ->assertForbidden();

        $this->assertDatabaseCount('demo_schedules', 0);
    }

    public function test_demo_end_time_must_be_after_start_time(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->fixtures($manager);
        $lead = $this->lead($manager, $fixtures);

        $this->actingAs($manager)
            ->from("/crm/leads/{$lead->id}/demos/create")
            ->post("/crm/leads/{$lead->id}/demos", $this->demoPayload($manager, [
                'start_time' => '11:00',
                'end_time' => '10:00',
            ]))
            ->assertRedirect("/crm/leads/{$lead->id}/demos/create")
            ->assertSessionHasErrors('end_time');
    }

    public function test_demo_can_be_rescheduled_completed_and_cancelled(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->fixtures($manager);
        $lead = $this->lead($manager, $fixtures);

        $this->actingAs($manager)
            ->post("/crm/leads/{$lead->id}/demos", $this->demoPayload($manager))
            ->assertRedirect();

        $demo = DemoSchedule::query()->firstOrFail();

        $this->actingAs($manager)
            ->patch("/crm/demos/{$demo->id}/reschedule", $this->demoPayload($manager, [
                'demo_date' => '2026-07-22',
                'start_time' => '14:00',
                'end_time' => '15:00',
            ]))
            ->assertRedirect("/crm/leads/{$lead->id}");

        $this->assertSame(DemoScheduleStatus::Rescheduled, $demo->refresh()->status);
        $this->assertSame('2026-07-22 14:00:00', $demo->starts_at?->format('Y-m-d H:i:s'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.demo.rescheduled', 'auditable_id' => $demo->id]);

        $this->actingAs($manager)
            ->post("/crm/demos/{$demo->id}/complete")
            ->assertRedirect("/crm/leads/{$lead->id}");

        $this->assertSame(DemoScheduleStatus::Completed, $demo->refresh()->status);
        $this->assertNotNull($demo->completed_at);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => 'Demo completed']);

        $this->actingAs($manager)
            ->post("/crm/leads/{$lead->id}/demos", $this->demoPayload($manager, ['demo_date' => '2026-07-25']))
            ->assertRedirect();

        $secondDemo = DemoSchedule::query()->latest('id')->firstOrFail();

        $this->actingAs($manager)
            ->post("/crm/demos/{$secondDemo->id}/cancel")
            ->assertRedirect("/crm/leads/{$lead->id}");

        $this->assertSame(DemoScheduleStatus::Cancelled, $secondDemo->refresh()->status);
        $this->assertNotNull($secondDemo->cancelled_at);
        $this->assertDatabaseHas('crm_activities', ['crm_lead_id' => $lead->id, 'subject' => 'Demo cancelled']);
    }

    public function test_demo_requests_page_shows_scheduled_demo_information_and_dashboard_metrics(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->fixtures($manager);
        $lead = $this->lead($manager, $fixtures, ['source_id' => $fixtures['bookDemo']->id]);

        $this->actingAs($manager)
            ->post("/crm/leads/{$lead->id}/demos", $this->demoPayload($manager, [
                'demo_date' => now()->format('Y-m-d'),
                'start_time' => now()->addHour()->format('H:i'),
                'end_time' => now()->addHours(2)->format('H:i'),
                'meeting_mode' => DemoMeetingMode::ExternalLink->value,
            ]))
            ->assertRedirect();

        $this->actingAs($manager)
            ->get('/crm/demo-requests')
            ->assertOk()
            ->assertSee('CRM Demo Lead')
            ->assertSee('Zoom / External Link')
            ->assertSee('Scheduled');

        $this->actingAs($manager)
            ->get('/crm')
            ->assertOk()
            ->assertSee('Demos Today')
            ->assertSee('Upcoming Demos');
    }

    /**
     * @return array<string, CrmLeadSource|CrmLeadStatus>
     */
    private function fixtures(User $user): array
    {
        $source = CrmLeadSource::create([
            'company_id' => $user->company_id,
            'name' => 'Website Contact',
            'slug' => 'website-contact',
            'is_active' => true,
        ]);
        $bookDemo = CrmLeadSource::create([
            'company_id' => $user->company_id,
            'name' => 'Book Demo',
            'slug' => 'book-demo',
            'is_active' => true,
        ]);
        $new = $this->leadStatus($user, 'New', LeadStageType::New, 1);
        $demo = $this->leadStatus($user, 'Demo Scheduled', LeadStageType::DemoScheduled, 2);

        return compact('source', 'bookDemo', 'new', 'demo');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function demoPayload(User $assignedUser, array $overrides = []): array
    {
        return array_merge([
            'demo_date' => '2026-07-20',
            'start_time' => '10:00',
            'end_time' => '10:30',
            'timezone' => 'UTC',
            'meeting_mode' => DemoMeetingMode::PhoneCall->value,
            'meeting_link' => null,
            'assigned_to' => $assignedUser->id,
            'customer_email' => 'demo@example.test',
            'customer_phone' => '+91 90000 11111',
            'notes' => 'Prepare branch and product questions.',
        ], $overrides);
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
            'title' => 'CRM Demo Lead',
            'business_name' => 'Demo Retail',
            'contact_name' => 'Asha Mehta',
            'email' => 'demo@example.test',
            'phone' => '+91 90000 11111',
            'priority' => LeadPriority::Medium->value,
        ], $overrides));
    }

    private function leadStatus(User $user, string $name, LeadStageType $stage, int $sortOrder): CrmLeadStatus
    {
        return CrmLeadStatus::create([
            'company_id' => $user->company_id,
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'stage_type' => $stage->value,
            'probability' => $stage === LeadStageType::DemoScheduled ? 60 : 10,
            'is_active' => true,
            'sort_order' => $sortOrder,
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

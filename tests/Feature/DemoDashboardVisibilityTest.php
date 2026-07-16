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
use App\Repositories\Crm\DemoScheduleRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDashboardVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_demo_appears_on_main_and_crm_dashboards_with_a_lead_link(): void
    {
        $manager = $this->manager();
        $lead = $this->lead($manager, 'Orchid Retail Demo');
        $this->schedule($manager, $lead, DemoScheduleStatus::Scheduled, now()->addDay());

        $this->actingAs($manager)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Demos Today')
            ->assertSee('Upcoming Demo Schedule')
            ->assertSee('Orchid Retail')
            ->assertSee('Phone Call')
            ->assertSee('/crm/leads/'.$lead->id, false);

        $this->actingAs($manager)
            ->get('/crm')
            ->assertOk()
            ->assertSee('Scheduled Demos')
            ->assertSee('Upcoming Demo Schedule')
            ->assertSee('Orchid Retail')
            ->assertSee('/crm/leads/'.$lead->id, false);
    }

    public function test_dashboard_metrics_count_active_demos_and_exclude_completed_or_cancelled_from_upcoming(): void
    {
        $this->travelTo('2026-07-16 09:00:00');

        try {
            $manager = $this->manager();
            $today = $this->schedule($manager, $this->lead($manager, 'Today Demo'), DemoScheduleStatus::Scheduled, now()->addHour());
            $upcoming = $this->schedule($manager, $this->lead($manager, 'Upcoming Demo'), DemoScheduleStatus::Rescheduled, now()->addDay());
            $this->schedule($manager, $this->lead($manager, 'Overdue Demo'), DemoScheduleStatus::Scheduled, now()->subHour());
            $completed = $this->schedule($manager, $this->lead($manager, 'Completed Demo'), DemoScheduleStatus::Completed, now()->addDays(2));
            $cancelled = $this->schedule($manager, $this->lead($manager, 'Cancelled Demo'), DemoScheduleStatus::Cancelled, now()->addDays(3));

            $repository = app(DemoScheduleRepository::class);
            $metrics = $repository->dashboardMetrics($manager);
            $upcomingDemos = $repository->upcomingForUser($manager);

            $this->assertSame(3, $metrics['scheduled_demos']);
            $this->assertSame(2, $metrics['demos_today']);
            $this->assertSame(2, $metrics['upcoming_demos']);
            $this->assertSame(1, $metrics['overdue_demos']);
            $this->assertSame(1, $metrics['completed_demos']);
            $this->assertSame(1, $metrics['cancelled_demos']);
            $this->assertCount(2, $upcomingDemos);
            $this->assertTrue($upcomingDemos->contains('id', $today->id));
            $this->assertTrue($upcomingDemos->contains('id', $upcoming->id));
            $this->assertFalse($upcomingDemos->contains('id', $completed->id));
            $this->assertFalse($upcomingDemos->contains('id', $cancelled->id));
        } finally {
            $this->travelBack();
        }
    }

    public function test_demo_requests_can_filter_and_sort_by_scheduled_date(): void
    {
        $manager = $this->manager();
        $laterLead = $this->lead($manager, 'Later Demo');
        $earlierLead = $this->lead($manager, 'Earlier Demo');

        $this->schedule($manager, $laterLead, DemoScheduleStatus::Scheduled, now()->addDays(3));
        $this->schedule($manager, $earlierLead, DemoScheduleStatus::Scheduled, now()->addDay());

        $sorted = $this->actingAs($manager)
            ->get('/crm/demo-requests?sort=scheduled_date')
            ->assertOk()
            ->assertSee('Earlier Demo')
            ->assertSee('Later Demo');

        $this->assertLessThan(
            strpos($sorted->getContent(), 'Later Demo'),
            strpos($sorted->getContent(), 'Earlier Demo'),
        );

        $this->actingAs($manager)
            ->get('/crm/demo-requests?scheduled_date='.now()->addDay()->toDateString())
            ->assertOk()
            ->assertSee('Earlier Demo')
            ->assertDontSee('Later Demo');
    }

    private function manager(): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => UserRole::Manager,
        ]);
    }

    private function lead(User $user, string $title): CrmLead
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
            'title' => $title,
            'business_name' => str($title)->replace(' Demo', ' Retail')->toString(),
            'contact_name' => 'Asha Mehta',
            'email' => str($title)->slug().'@example.test',
            'phone' => '+91 90000 11111',
            'priority' => LeadPriority::Medium,
        ]);
    }

    private function schedule(User $user, CrmLead $lead, DemoScheduleStatus $status, Carbon $startsAt): DemoSchedule
    {
        return DemoSchedule::create([
            'company_id' => $user->company_id,
            'lead_id' => $lead->id,
            'assigned_to' => $user->id,
            'scheduled_by' => $user->id,
            'title' => 'Demo: '.$lead->title,
            'scheduled_date' => $startsAt->toDateString(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'timezone' => 'UTC',
            'meeting_mode' => DemoMeetingMode::PhoneCall,
            'status' => $status,
            'completed_at' => $status === DemoScheduleStatus::Completed ? now() : null,
            'cancelled_at' => $status === DemoScheduleStatus::Cancelled ? now() : null,
        ]);
    }
}

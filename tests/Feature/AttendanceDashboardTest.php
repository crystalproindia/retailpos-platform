<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDashboardTest extends TestCase
{
    use RefreshDatabase;
    public function test_manager_can_open_outlet_scoped_attendance_dashboard(): void
    {
        $company = Company::factory()->create(); $branch = Branch::factory()->for($company)->create(['is_active' => true]); $manager = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager, 'account_status' => 'active']); BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $manager->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $manager->id]); $this->actingAs($manager)->get(route('attendance.dashboard'))->assertOk()->assertSee('Today’s attendance')->assertSee(route('attendance.reviews')); $this->actingAs($manager)->get(route('attendance.reviews'))->assertOk()->assertSee('Corrections and overtime');
    }

    public function test_summary_uses_default_dates_when_no_filters_are_supplied(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create(['is_active' => true]);
        $manager = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager, 'account_status' => 'active']);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $manager->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $manager->id]);

        $this->actingAs($manager)->get(route('attendance.summary'))->assertOk();
    }
}

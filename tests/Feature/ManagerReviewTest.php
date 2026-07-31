<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkforceEmployee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_review_a_direct_report_but_not_an_unrelated_employee(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create(['is_primary' => true]);
        $managerProfile = WorkforceEmployee::create(['company_id' => $company->id, 'primary_branch_id' => $branch->id, 'employee_number' => 'MGR-001', 'first_name' => 'Mira', 'display_name' => 'Mira', 'status' => 'active']);
        $manager = User::factory()->for($company)->create(['branch_id' => $branch->id, 'workforce_employee_id' => $managerProfile->id, 'role' => UserRole::Manager, 'account_status' => 'active']);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'user_id' => $manager->id, 'is_active' => true, 'is_default' => true, 'assigned_by' => $manager->id]);
        $report = WorkforceEmployee::create(['company_id' => $company->id, 'primary_branch_id' => $branch->id, 'reporting_manager_id' => $managerProfile->id, 'employee_number' => 'REP-001', 'first_name' => 'Report', 'display_name' => 'Direct report', 'status' => 'active']);
        $other = WorkforceEmployee::create(['company_id' => $company->id, 'primary_branch_id' => $branch->id, 'employee_number' => 'REP-002', 'first_name' => 'Other', 'display_name' => 'Other employee', 'status' => 'active']);
        $payload = ['period_starts_at' => now()->startOfMonth()->toDateString(), 'period_ends_at' => now()->toDateString(), 'cycle' => 'monthly', 'status' => 'submitted', 'customer_service' => 4, 'comments' => 'Consistent customer handover.'];

        $this->actingAs($manager)->post(route('workforce.reviews.store', $report), $payload)->assertRedirect();
        $this->assertDatabaseHas('workforce_manager_reviews', ['employee_id' => $report->id, 'reviewer_user_id' => $manager->id, 'status' => 'submitted']);
        $this->actingAs($manager)->post(route('workforce.reviews.store', $other), $payload)->assertForbidden();
    }
}

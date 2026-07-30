<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_open_the_authorized_reports_hub_and_staff_cannot(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $administrator = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'role' => UserRole::Administrator, 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'role' => UserRole::Staff, 'is_active' => true]);

        $this->actingAs($administrator)->get('/reports?outlet_id=all')->assertOk()->assertSee('All Outlets');
        $this->actingAs($staff)->get('/reports')->assertForbidden();
    }

    public function test_manager_cannot_request_all_outlets_or_another_tenants_outlet(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $manager = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'role' => UserRole::Manager, 'is_active' => true]);
        $other = Branch::factory()->create(['company_id' => Company::factory()->create()->id, 'is_active' => true]);

        $this->actingAs($manager)->get('/reports?outlet_id=all')->assertSessionHasErrors('outlet_id');
        $this->actingAs($manager)->get('/reports?outlet_id='.$other->id)->assertSessionHasErrors('outlet_id');
    }
}

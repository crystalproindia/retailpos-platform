<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_exports_share_authorization_and_reject_formula_like_filter_values(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $manager = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'role' => UserRole::Manager, 'is_active' => true]);
        $staff = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'role' => UserRole::Staff, 'is_active' => true]);

        $this->actingAs($manager)->get('/reports/sales/export?outlet_id='.$branch->id)->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->actingAs($manager)->get('/reports/sales/export?outlet_id=all')->assertSessionHasErrors('outlet_id');
        $this->actingAs($staff)->get('/reports/sales/export?outlet_id='.$branch->id)->assertForbidden();
    }
}

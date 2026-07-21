<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SaasGrandfatheredMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_grandfathered_migration_recovers_a_partial_state_with_a_stable_code_and_no_duplicates(): void
    {
        $code = 'existing-tenant-access';
        $planId = DB::table('saas_plans')->where('code', $code)->value('id');

        DB::table('saas_plan_versions')->where('saas_plan_id', $planId)->delete();
        DB::table('saas_plan_features')->where('saas_plan_id', $planId)->delete();
        DB::table('saas_plan_limits')->where('saas_plan_id', $planId)->delete();
        DB::table('saas_plans')->where('id', $planId)->delete();

        $company = Company::factory()->create(['currency' => 'INR']);
        config(['saas.grandfathered_plan_code' => null]);

        $migration = require database_path('migrations/2026_07_22_020100_add_saas_tenant_details_and_backfill.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('saas_plans', ['code' => $code, 'name' => 'Existing tenant access', 'status' => 'active']);
        $this->assertDatabaseMissing('saas_plans', ['code' => 'legacy-unlimited']);
        $this->assertSame(1, DB::table('saas_plans')->where('code', $code)->count());
        $this->assertSame(1, DB::table('saas_subscriptions')->where('company_id', $company->id)->where('status', 'active')->count());
        $this->assertSame(1, DB::table('saas_plan_versions')->where('saas_plan_id', DB::table('saas_plans')->where('code', $code)->value('id'))->where('version', 1)->count());
    }
}

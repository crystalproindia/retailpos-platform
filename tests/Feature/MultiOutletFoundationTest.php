<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiOutletFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_an_outlet_with_a_tenant_scoped_warehouse_and_assignment(): void
    {
        [$manager, $main] = $this->userWithMainOutlet(UserRole::Manager);

        $this->actingAs($manager)->post('/settings/outlets', $this->payload('CBE-02', 'Coimbatore Outlet'))->assertRedirect();

        $outlet = Branch::query()->where('company_id', $manager->company_id)->where('code', 'CBE-02')->firstOrFail();
        $this->assertFalse($outlet->is_primary);
        $this->assertDatabaseHas('warehouses', ['company_id' => $manager->company_id, 'branch_id' => $outlet->id]);
        $this->assertDatabaseHas('branch_user_assignments', ['company_id' => $manager->company_id, 'branch_id' => $outlet->id, 'user_id' => $manager->id]);
        $this->assertSame($main->id, Branch::query()->where('company_id', $manager->company_id)->where('is_primary', true)->sole()->id);
    }

    public function test_duplicate_code_cross_tenant_access_and_unassigned_context_are_rejected(): void
    {
        [$manager] = $this->userWithMainOutlet(UserRole::Manager);
        $this->actingAs($manager)->post('/settings/outlets', $this->payload('CBE-02', 'Coimbatore Outlet'))->assertRedirect();
        $this->actingAs($manager)->post('/settings/outlets', $this->payload('CBE-02', 'Duplicate Outlet'))->assertSessionHasErrors('code');
        $outlet = Branch::query()->where('company_id', $manager->company_id)->where('code', 'CBE-02')->firstOrFail();

        [$otherManager] = $this->userWithMainOutlet(UserRole::Manager);
        $this->actingAs($otherManager)->get("/settings/outlets/{$outlet->id}/edit")->assertNotFound();

        $cashier = User::factory()->for($manager->company)->create(['branch_id' => Branch::query()->where('company_id', $manager->company_id)->where('is_primary', true)->value('id'), 'role' => UserRole::Sales]);
        $this->actingAs($cashier)->from('/dashboard')->post('/outlet-context', ['outlet_id' => $outlet->id])->assertRedirect('/dashboard')->assertSessionHasErrors('outlet_id');
    }

    public function test_default_and_archive_rules_preserve_one_active_outlet_and_history(): void
    {
        [$manager, $main] = $this->userWithMainOutlet(UserRole::Manager);
        $this->actingAs($manager)->post('/settings/outlets', $this->payload('CBE-02', 'Coimbatore Outlet'))->assertRedirect();
        $second = Branch::query()->where('company_id', $manager->company_id)->where('code', 'CBE-02')->firstOrFail();

        $this->actingAs($manager)->post("/settings/outlets/{$main->id}/archive")->assertSessionHasErrors('outlet');
        $this->actingAs($manager)->post("/settings/outlets/{$second->id}/make-default")->assertRedirect();
        $this->assertTrue($second->refresh()->is_primary);
        $this->actingAs($manager)->post("/settings/outlets/{$main->id}/archive")->assertRedirect();
        $this->assertFalse($main->refresh()->is_active);
        $this->actingAs($manager)->post("/settings/outlets/{$second->id}/archive")->assertSessionHasErrors('outlet');
        $this->actingAs($manager)->post("/settings/outlets/{$main->id}/restore")->assertRedirect();
        $this->assertTrue($main->refresh()->is_active);
    }

    public function test_assigned_user_resolves_a_default_outlet_without_requiring_a_picker(): void
    {
        [$manager, $main] = $this->userWithMainOutlet(UserRole::Manager);
        $second = Branch::factory()->for($manager->company)->create(['code' => 'CBE-02']);
        $sales = User::factory()->for($manager->company)->create(['branch_id' => $main->id, 'role' => UserRole::Sales]);
        BranchUserAssignment::create(['company_id' => $manager->company_id, 'branch_id' => $second->id, 'user_id' => $sales->id, 'is_default' => true, 'is_active' => true, 'assigned_by' => $manager->id]);

        $this->assertSame($second->id, app(OutletAccessService::class)->current($sales)->id);
        $this->assertTrue(app(OutletAccessService::class)->canAccess($sales, $second));
        $this->assertFalse(app(OutletAccessService::class)->canAccess($sales, $main));
    }

    /** @return array{User, Branch} */
    private function userWithMainOutlet(UserRole $role): array
    {
        $company = Company::factory()->create();
        $main = Branch::factory()->for($company)->create(['code' => 'MAIN', 'is_primary' => true]);
        $user = User::factory()->for($company)->create(['branch_id' => $main->id, 'role' => $role]);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $main->id, 'user_id' => $user->id, 'is_default' => true, 'is_active' => true, 'assigned_by' => $user->id]);
        return [$user, $main];
    }

    /** @return array<string, string> */
    private function payload(string $code, string $name): array
    {
        return ['name' => $name, 'code' => $code, 'city' => 'Coimbatore', 'state' => 'Tamil Nadu', 'country' => 'India', 'invoice_prefix' => 'CBE', 'receipt_prefix' => 'CBE-POS'];
    }

}

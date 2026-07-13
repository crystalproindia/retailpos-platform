<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerGroup;
use App\Models\Customers\CustomerSetting;
use App\Models\User;
use App\Services\Customers\CustomerInsightService;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_module_registry_has_children_and_honours_roles(): void
    {
        $registry = new ModuleRegistry;
        $module = $registry->find('customers');
        $salesSidebar = $registry->sidebar(UserRole::Sales);
        $staffSidebar = $registry->sidebar(UserRole::Staff);
        $customerChildren = collect($registry->sidebar(UserRole::Administrator)->firstWhere('id', 'customers')->children);

        $this->assertSame('customers.dashboard', $module->route);
        $this->assertTrue($salesSidebar->contains('id', 'customers'));
        $this->assertFalse($staffSidebar->contains('id', 'customers'));
        $this->assertContains('customer-groups', $customerChildren->pluck('id'));
        $this->assertContains('customer-settings', $customerChildren->pluck('id'));
        $this->assertContains('customer-returns', $customerChildren->pluck('id'));
    }

    public function test_customer_roles_allow_sales_to_work_records_but_block_management_actions(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);

        $this->actingAs($sales)->get('/customers')->assertOk();
        $this->actingAs($sales)->get("/customers/{$customer->id}")->assertOk();
        $this->actingAs($sales)->get("/customers/{$customer->id}/edit")->assertOk();
        $this->actingAs($sales)->delete("/customers/{$customer->id}")->assertForbidden();
        $this->actingAs($sales)->post("/customers/{$customer->id}/wallet-adjustments", ['amount' => 100, 'description' => 'Test'])->assertForbidden();
        $this->actingAs($staff)->get('/customers')->assertForbidden();
    }

    public function test_customer_crud_assignments_and_lifecycle_are_tenant_scoped(): void
    {
        $manager = $this->user(UserRole::Manager);
        $payload = $this->customerPayload(['email' => 'mira@example.test']);

        $this->actingAs($manager)->post('/customers', $payload)->assertRedirect();

        $customer = Customer::query()->where('email', 'mira@example.test')->firstOrFail();
        $this->assertSame('CUS-000001', $customer->customer_number);
        $this->assertDatabaseHas('customer_loyalty_accounts', ['customer_id' => $customer->id]);
        $this->assertDatabaseHas('customer_activity_logs', ['customer_id' => $customer->id, 'activity_type' => 'created']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'customer.created', 'auditable_id' => $customer->id]);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'customer.created', 'aggregate_id' => $customer->id]);

        $this->actingAs($manager)->put("/customers/{$customer->id}", $this->customerPayload([
            'first_name' => 'Mira Updated',
            'email' => 'mira@example.test',
        ]))->assertRedirect();
        $this->assertSame('Mira Updated', $customer->refresh()->first_name);

        $group = CustomerGroup::create(['company_id' => $manager->company_id, 'name' => 'VIP Members', 'slug' => 'vip-members', 'is_active' => true]);
        $this->actingAs($manager)->post("/customers/{$customer->id}/groups", ['customer_group_id' => $group->id])->assertRedirect();
        $this->assertDatabaseHas('customer_group_members', ['customer_id' => $customer->id, 'customer_group_id' => $group->id]);

        $outsider = $this->user(UserRole::Manager);
        $this->actingAs($outsider)->get("/customers/{$customer->id}")->assertNotFound();

        $this->actingAs($manager)->delete("/customers/{$customer->id}")->assertRedirect();
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
        $this->actingAs($manager)->post("/customers/{$customer->id}/restore")->assertRedirect();
        $this->assertFalse($customer->refresh()->trashed());
    }

    public function test_loyalty_and_wallet_ledgers_are_auditable_and_guard_negative_balances(): void
    {
        $manager = $this->user(UserRole::Manager);
        $customer = $this->customer($manager);

        $this->actingAs($manager)->post("/customers/{$customer->id}/loyalty-adjustments", ['amount' => 125, 'description' => 'Welcome points'])->assertRedirect();
        $this->actingAs($manager)->post("/customers/{$customer->id}/wallet-adjustments", ['amount' => 200, 'description' => 'Wallet credit'])->assertRedirect();

        $this->assertSame(125, $customer->refresh()->loyalty_points_balance);
        $this->assertSame(200.0, (float) $customer->wallet_balance);
        $this->assertDatabaseHas('customer_loyalty_transactions', ['customer_id' => $customer->id, 'points' => 125]);
        $this->assertDatabaseHas('customer_wallet_transactions', ['customer_id' => $customer->id, 'amount' => 200]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'customer.loyalty.points_adjusted']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'customer.wallet.adjusted']);

        $this->actingAs($manager)->post("/customers/{$customer->id}/wallet-adjustments", ['amount' => -300, 'description' => 'Invalid debit'])->assertSessionHasErrors('amount');
    }

    public function test_customer_intelligence_flags_birthday_inactivity_loss_and_frequent_returns(): void
    {
        $manager = $this->user(UserRole::Manager);
        CustomerSetting::create(['company_id' => $manager->company_id, 'birthday_reminder_days_before' => 7, 'inactive_customer_days' => 90, 'lost_customer_days' => 180, 'frequent_return_threshold_count' => 3]);
        $customer = $this->customer($manager, [
            'date_of_birth' => now()->addDays(2)->format('Y-m-d'),
            'last_purchase_at' => now()->subDays(200),
            'last_return_at' => now()->subDays(4),
            'total_purchase_amount' => 15000,
            'total_orders_count' => 18,
            'total_return_amount' => 1100,
            'total_returns_count' => 3,
        ]);

        app(CustomerInsightService::class)->calculate($customer, $manager);

        $this->assertDatabaseHas('customer_insight_snapshots', [
            'customer_id' => $customer->id,
            'is_inactive_90_days' => true,
            'is_lost_customer' => true,
            'is_frequent_returner' => true,
        ]);
        $this->actingAs($manager)->get('/customers/birthdays/upcoming')->assertOk()->assertSee($customer->display_name);
        $this->actingAs($manager)->get('/customers/inactive/list')->assertOk()->assertSee($customer->display_name);
        $this->actingAs($manager)->get('/customers/lost/list')->assertOk()->assertSee($customer->display_name);
        $this->actingAs($manager)->get('/customers/returns/frequent')->assertOk()->assertSee($customer->display_name);
        $this->actingAs($manager)->post('/customers/insights/refresh')->assertRedirect();
    }

    public function test_database_seeder_creates_customer_foundation_demo_records(): void
    {
        $this->seed();

        $company = Company::query()->where('name', 'Crystal Retail Demo')->firstOrFail();

        $this->assertDatabaseHas('customer_settings', ['company_id' => $company->id]);
        $this->assertDatabaseHas('customer_groups', ['company_id' => $company->id, 'slug' => 'vip']);
        $this->assertDatabaseHas('customers', ['company_id' => $company->id, 'customer_number' => 'CUS-001001']);
        $this->assertDatabaseHas('customer_loyalty_accounts', ['company_id' => $company->id]);
        $this->assertDatabaseHas('customer_insight_snapshots', ['company_id' => $company->id]);
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }

    /** @param array<string, mixed> $overrides */
    private function customer(User $user, array $overrides = []): Customer
    {
        return Customer::create($overrides + [
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'customer_number' => 'CUS-'.str_pad((string) (Customer::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'first_name' => 'Demo',
            'last_name' => 'Customer',
            'display_name' => 'Demo Customer',
            'customer_type' => 'retail',
            'status' => 'active',
            'created_by' => $user->id,
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function customerPayload(array $overrides = []): array
    {
        return $overrides + [
            'first_name' => 'Mira',
            'last_name' => 'Shah',
            'customer_type' => 'retail',
            'status' => 'active',
            'phone' => '9876543210',
            'city' => 'Mumbai',
            'country' => 'India',
            'tags' => ['demo'],
        ];
    }
}

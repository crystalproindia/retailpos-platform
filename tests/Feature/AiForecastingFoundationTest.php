<?php

namespace Tests\Feature;

use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Models\Ai\AiForecastResult;
use App\Models\Ai\AiForecastRun;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Customers\Customer;
use App\Models\Inventory\Product;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\StockLevel;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSaleItem;
use App\Models\User;
use App\Services\Ai\AiForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiForecastingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_forecast_uses_completed_history_and_excludes_voided_sales(): void
    {
        [$company, $branch, $admin] = $this->account();
        foreach (range(1, 14) as $day) $this->sale($company, $branch, now()->subDays($day), 1000 + $day);
        $this->sale($company, $branch, now()->subDay(), 999999, 'voided');

        $run = app(AiForecastService::class)->run($company->id, 'sales', $admin)->get('sales');

        $this->assertSame('completed', $run->status);
        $this->assertSame(3, $run->results()->count());
        $this->assertLessThan(100000, (float) $run->results()->first()->predicted_value);
        $this->assertDatabaseMissing('ai_forecast_results', ['predicted_value' => 999999]);
    }

    public function test_insufficient_history_is_safe_and_does_not_fabricate_sales_forecasts(): void
    {
        [$company, $branch] = $this->account();
        $this->sale($company, $branch, now()->subDay(), 1000);
        $run = app(AiForecastService::class)->run($company->id, 'sales')->get('sales');
        $this->assertSame('insufficient_data', $run->status);
        $this->assertSame(0, $run->results()->count());
    }

    public function test_inventory_reorder_is_advisory_and_never_negative(): void
    {
        [$company, $branch, $admin] = $this->account();
        $unit = InventoryUnit::create(['company_id' => $company->id, 'name' => 'Each', 'short_code' => 'ea', 'is_active' => true]);
        $warehouse = Warehouse::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $product = Product::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'unit_id' => $unit->id, 'name' => 'Forecast item', 'slug' => 'forecast-item', 'sku' => 'F-1', 'is_active' => true, 'track_inventory' => true]);
        StockLevel::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id, 'quantity_available' => 2, 'quantity_on_hand' => 2, 'average_daily_sales' => 1]);
        foreach (range(1, 6) as $day) { $sale = $this->sale($company, $branch, now()->subDays($day), 50); PosSaleItem::create(['company_id' => $company->id, 'pos_sale_id' => $sale->id, 'product_id' => $product->id, 'product_name' => $product->name, 'sku' => $product->sku, 'quantity' => 2, 'unit_price' => 25, 'line_total' => 50]); }

        app(AiForecastService::class)->run($company->id, 'inventory', $admin);
        $result = AiForecastResult::query()->where('company_id', $company->id)->where('product_id', $product->id)->firstOrFail();
        $this->assertSame('stockout_risk', $result->classification);
        $this->assertGreaterThanOrEqual(0, (float) data_get($result->supporting_metrics, 'suggested_reorder_quantity'));
        $this->assertTrue((bool) data_get($result->explanation, 'advisory_only'));
    }

    public function test_customer_segments_and_crm_priorities_are_private_and_explainable(): void
    {
        [$company, $branch, $admin] = $this->account();
        $customer = Customer::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'customer_number' => 'C-1', 'first_name' => 'Private', 'display_name' => 'Private Customer', 'status' => 'active', 'total_orders_count' => 9, 'total_purchase_amount' => 150000, 'last_purchase_at' => now()->subDays(3)]);
        $status = CrmLeadStatus::create(['company_id' => $company->id, 'name' => 'New', 'slug' => 'new', 'stage_type' => LeadStageType::New->value, 'probability' => 0, 'is_active' => true]);
        $lead = CrmLead::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'status_id' => $status->id, 'assigned_user_id' => $admin->id, 'created_by' => $admin->id, 'title' => 'Priority lead', 'priority' => LeadPriority::Medium, 'currency' => 'INR', 'buying_interest_rating' => 5, 'follow_up_urgency_rating' => 4, 'next_follow_up_at' => now()->subDay()]);

        app(AiForecastService::class)->run($company->id, 'customers', $admin);
        app(AiForecastService::class)->run($company->id, 'crm', $admin);
        $customerResult = AiForecastResult::query()->where('customer_id', $customer->id)->firstOrFail();
        $leadResult = AiForecastResult::query()->where('lead_id', $lead->id)->firstOrFail();
        $this->assertSame('high_value', $customerResult->classification);
        $this->assertSame('high_priority', $leadResult->classification);
        $this->assertStringContainsString('overdue', data_get($leadResult->explanation, 'plain_language'));
        $this->assertArrayNotHasKey('email', $customerResult->supporting_metrics);
    }

    public function test_dashboard_is_authorized_and_tenant_isolated(): void
    {
        [$company, $branch, $admin] = $this->account();
        [$otherCompany, $otherBranch] = $this->account();
        $manager = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $this->sale($company, $branch, now()->subDay(), 1000);
        $this->actingAs($admin)->get('/ai')->assertOk();
        $this->actingAs($manager)->post('/ai/run', ['type' => 'sales'])->assertForbidden();
        app(AiForecastService::class)->run($company->id, 'sales', $admin);
        $this->assertSame(0, AiForecastRun::query()->where('company_id', $otherCompany->id)->count());
    }

    private function account(): array
    {
        $company = Company::factory()->create(); $branch = Branch::factory()->create(['company_id' => $company->id]); $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id, 'role' => UserRole::Administrator]); return [$company, $branch, $user];
    }
    private function sale(Company $company, Branch $branch, $soldAt, float $total, string $status = 'completed'): PosSale
    {
        return PosSale::create(['company_id' => $company->id, 'branch_id' => $branch->id, 'sale_number' => 'S-'.uniqid(), 'status' => $status, 'currency' => 'INR', 'total_amount' => $total, 'sold_at' => $soldAt, 'completed_at' => $status === 'completed' ? $soldAt : null]);
    }
}

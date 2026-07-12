<?php

namespace Tests\Feature;

use App\Enums\Promotions\PromotionActionType;
use App\Enums\Promotions\PromotionStatus;
use App\Enums\Promotions\PromotionType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Inventory\InventoryBrand;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\SalesChannel;
use App\Models\Promotions\PromotionBrandTarget;
use App\Models\Promotions\PromotionBranchTarget;
use App\Models\Promotions\PromotionCampaign;
use App\Models\Promotions\PromotionCategoryTarget;
use App\Models\Promotions\PromotionChannelTarget;
use App\Models\Promotions\PromotionCoupon;
use App\Models\Promotions\PromotionProductTarget;
use App\Models\Promotions\PromotionRule;
use App\Models\Promotions\PromotionSettings;
use App\Models\Promotions\PromotionSimulation;
use App\Models\User;
use App\Services\Promotions\PromotionRuleEngine;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_promotions_module_registry_and_read_only_sales_access(): void
    {
        $registry = new ModuleRegistry;
        $manager = $this->user(UserRole::Manager);
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);

        $this->assertSame('promotions.dashboard', $registry->find('promotions')->route);
        $this->assertContains('promotion-simulator', collect($registry->sidebar(UserRole::Administrator)->firstWhere('id', 'promotions')->children)->pluck('id'));
        $this->assertTrue($registry->sidebar(UserRole::Sales)->contains('id', 'promotions'));
        $this->assertFalse($registry->sidebar(UserRole::Staff)->contains('id', 'promotions'));
        $this->actingAs($sales)->get('/promotions')->assertOk();
        $this->actingAs($sales)->get('/promotions/rules/create')->assertForbidden();
        $this->actingAs($staff)->get('/promotions')->assertForbidden();
    }

    public function test_campaign_crud_soft_delete_restore_and_tenant_isolation(): void
    {
        $manager = $this->user(UserRole::Manager);
        $payload = ['name' => 'Monsoon Sale', 'slug' => 'monsoon-sale', 'campaign_type' => 'seasonal', 'status' => 'draft', 'priority' => 100];
        $this->actingAs($manager)->post('/promotions/campaigns', $payload)->assertRedirect();
        $campaign = PromotionCampaign::query()->firstOrFail();
        $this->actingAs($manager)->put("/promotions/campaigns/{$campaign->id}", array_merge($payload, ['name' => 'Updated Monsoon Sale']))->assertRedirect();
        $this->assertSame('Updated Monsoon Sale', $campaign->refresh()->name);
        $this->actingAs($manager)->delete("/promotions/campaigns/{$campaign->id}")->assertRedirect();
        $this->assertSoftDeleted('promotion_campaigns', ['id' => $campaign->id]);
        $this->actingAs($manager)->post("/promotions/campaigns/{$campaign->id}/restore")->assertRedirect();
        $this->assertFalse($campaign->refresh()->trashed());
        $outsider = $this->user(UserRole::Manager);
        $this->actingAs($outsider)->get("/promotions/campaigns/{$campaign->id}")->assertNotFound();
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'promotion.campaign.updated']);
    }

    public function test_rule_crud_activation_pause_and_simulation_history(): void
    {
        $manager = $this->user(UserRole::Manager);
        $fixtures = $this->fixtures($manager);
        $payload = $this->rulePayload($fixtures, ['name' => 'Ten percent demo', 'slug' => 'ten-percent-demo']);
        $this->actingAs($manager)->post('/promotions/rules', $payload)->assertRedirect();
        $rule = PromotionRule::query()->firstOrFail();
        $this->actingAs($manager)->post("/promotions/rules/{$rule->id}/activate")->assertRedirect();
        $this->assertTrue($rule->refresh()->is_active);
        $this->actingAs($manager)->post("/promotions/rules/{$rule->id}/pause")->assertRedirect();
        $this->assertSame(PromotionStatus::Paused, $rule->refresh()->status);
        $this->actingAs($manager)->post('/promotions/simulator', ['branch_id' => $manager->branch_id, 'sales_channel_id' => $fixtures['channel']->id, 'items' => [['product_id' => $fixtures['product']->id, 'quantity' => 2, 'unit_price' => 100]]])->assertRedirect();
        $this->assertDatabaseCount('promotion_simulations', 1);
        $this->assertDatabaseHas('domain_event_logs', ['event_key' => 'promotion.simulation.ran']);
    }

    public function test_buy_x_get_y_calculations(): void
    {
        $manager = $this->user(UserRole::Manager); $fixtures = $this->fixtures($manager);
        foreach ([[1, 1, 2, 100], [1, 2, 3, 200], [2, 1, 3, 100], [2, 3, 5, 300]] as [$buy, $get, $quantity, $expected]) {
            $this->rule($manager, ['priority' => 100 + $get, 'promotion_type' => PromotionType::BuyXGetY->value], ['action_type' => PromotionActionType::FreeQuantity->value, 'buy_quantity' => $buy, 'get_quantity' => $get]);
            $result = app(PromotionRuleEngine::class)->evaluate($manager->company_id, $this->cart($fixtures, $quantity));
            $this->assertSame((float) $expected, $result['total_discount']);
            PromotionRule::query()->delete();
        }
    }

    public function test_quantity_and_minimum_bill_discounts_and_no_negative_total(): void
    {
        $manager = $this->user(UserRole::Manager); $fixtures = $this->fixtures($manager);
        $this->rule($manager, ['promotion_type' => PromotionType::QuantityDiscount->value, 'minimum_quantity' => 3], ['action_type' => PromotionActionType::PercentageOff->value, 'discount_percentage' => 20]);
        $this->assertSame(60.0, app(PromotionRuleEngine::class)->evaluate($manager->company_id, $this->cart($fixtures, 3))['total_discount']);
        PromotionRule::query()->delete();
        $this->rule($manager, ['promotion_type' => PromotionType::MinimumBillDiscount->value, 'minimum_bill_amount' => 1000], ['action_type' => PromotionActionType::PercentageOff->value, 'discount_percentage' => 10]);
        $result = app(PromotionRuleEngine::class)->evaluate($manager->company_id, $this->cart($fixtures, 12));
        $this->assertSame(120.0, $result['total_discount']);
        $this->assertGreaterThanOrEqual(0, $result['total_after_discount']);
    }

    public function test_product_category_brand_branch_and_channel_targeting(): void
    {
        $manager = $this->user(UserRole::Manager); $fixtures = $this->fixtures($manager);
        $rule = $this->rule($manager, [], ['action_type' => PromotionActionType::PercentageOff->value, 'discount_percentage' => 10]);
        PromotionProductTarget::create(['company_id' => $manager->company_id, 'promotion_rule_id' => $rule->id, 'product_id' => $fixtures['product']->id, 'include_or_exclude' => 'include']);
        PromotionCategoryTarget::create(['company_id' => $manager->company_id, 'promotion_rule_id' => $rule->id, 'category_id' => $fixtures['category']->id, 'include_or_exclude' => 'include']);
        PromotionBrandTarget::create(['company_id' => $manager->company_id, 'promotion_rule_id' => $rule->id, 'brand_id' => $fixtures['brand']->id, 'include_or_exclude' => 'include']);
        PromotionBranchTarget::create(['company_id' => $manager->company_id, 'promotion_rule_id' => $rule->id, 'branch_id' => $manager->branch_id, 'include_or_exclude' => 'include']);
        PromotionChannelTarget::create(['company_id' => $manager->company_id, 'promotion_rule_id' => $rule->id, 'sales_channel_id' => $fixtures['channel']->id, 'include_or_exclude' => 'include']);
        $this->assertSame(10.0, app(PromotionRuleEngine::class)->evaluate($manager->company_id, $this->cart($fixtures, 1))['total_discount']);
        $badCart = $this->cart($fixtures, 1); $badCart['sales_channel_id'] = 0;
        $this->assertSame(0.0, app(PromotionRuleEngine::class)->evaluate($manager->company_id, $badCart)['total_discount']);
    }

    public function test_coupon_validation_limit_stacking_exclusive_and_discount_cap(): void
    {
        $manager = $this->user(UserRole::Manager); $fixtures = $this->fixtures($manager);
        PromotionSettings::create(['company_id' => $manager->company_id, 'allow_stacking' => true, 'allow_coupon_with_auto_discount' => false, 'max_discount_amount_per_bill' => 15]);
        $auto = $this->rule($manager, ['priority' => 200], ['action_type' => PromotionActionType::PercentageOff->value, 'discount_percentage' => 20]);
        $couponRule = $this->rule($manager, ['priority' => 100, 'requires_coupon' => true, 'auto_apply' => false], ['action_type' => PromotionActionType::PercentageOff->value, 'discount_percentage' => 10]);
        PromotionCoupon::create(['company_id' => $manager->company_id, 'promotion_rule_id' => $couponRule->id, 'code' => 'SAVE10', 'usage_limit_total' => 1, 'used_count' => 0, 'is_active' => true]);
        $cart = $this->cart($fixtures, 1); $cart['coupon_code'] = 'SAVE10';
        $result = app(PromotionRuleEngine::class)->evaluate($manager->company_id, $cart);
        $this->assertSame(10.0, $result['total_discount']);
        $this->assertCount(1, $result['applied_promotions']);
        $this->assertNotEmpty($result['rejected_promotions']);
        $coupon = PromotionCoupon::query()->firstOrFail(); $coupon->update(['used_count' => 1]);
        $this->assertStringContainsString('usage limit', app(PromotionRuleEngine::class)->evaluate($manager->company_id, $cart)['rejected_promotions'][0]['reason']);
    }

    public function test_coupon_crud_validation_and_promotion_tenant_isolation(): void
    {
        $manager = $this->user(UserRole::Manager); $fixtures = $this->fixtures($manager); $rule = $this->rule($manager, ['requires_coupon' => true, 'auto_apply' => false], ['action_type' => PromotionActionType::PercentageOff->value, 'discount_percentage' => 10]);
        $this->actingAs($manager)->post('/promotions/coupons', ['promotion_rule_id' => $rule->id, 'code' => 'FESTIVE10', 'is_active' => '1'])->assertRedirect();
        $coupon = PromotionCoupon::query()->firstOrFail();
        $this->actingAs($manager)->post("/promotions/coupons/{$coupon->id}/toggle")->assertRedirect();
        $this->assertFalse($coupon->refresh()->is_active);
        $other = $this->user(UserRole::Manager);
        $this->actingAs($other)->get("/promotions/rules/{$rule->id}")->assertNotFound();
        $this->actingAs($manager)->post('/promotions/rules', $this->rulePayload($fixtures, ['campaign_id' => 999999]))->assertSessionHasErrors('campaign_id');
    }

    public function test_seeded_demo_promotions_are_available(): void
    {
        $this->seed();
        $company = Company::query()->where('name', 'Crystal Retail Demo')->firstOrFail();
        $this->assertDatabaseHas('promotion_campaigns', ['company_id' => $company->id, 'slug' => 'demo-festive-retail-offers']);
        $this->assertDatabaseHas('promotion_rules', ['company_id' => $company->id, 'slug' => 'demo-buy-1-get-1-free']);
        $this->assertDatabaseHas('promotion_coupons', ['company_id' => $company->id, 'code' => 'FESTIVE10']);
    }

    private function user(UserRole $role, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create(); $branch ??= Branch::factory()->for($company)->create();
        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }

    /** @return array<string, mixed> */
    private function fixtures(User $user): array
    {
        $category = InventoryCategory::create(['company_id' => $user->company_id, 'name' => 'Promotion Category', 'slug' => 'promotion-category', 'is_active' => true]);
        $brand = InventoryBrand::create(['company_id' => $user->company_id, 'name' => 'Promotion Brand', 'slug' => 'promotion-brand', 'is_active' => true]);
        $unit = InventoryUnit::create(['company_id' => $user->company_id, 'name' => 'Piece', 'short_code' => 'PCS', 'type' => 'quantity', 'is_active' => true]);
        $product = Product::create(['company_id' => $user->company_id, 'branch_id' => $user->branch_id, 'category_id' => $category->id, 'brand_id' => $brand->id, 'unit_id' => $unit->id, 'type' => 'simple', 'name' => 'Promotion Product', 'slug' => 'promotion-product', 'sku' => 'PROMO-001', 'selling_price' => 100, 'track_inventory' => false, 'status' => 'active', 'is_active' => true]);
        $channel = SalesChannel::create(['company_id' => $user->company_id, 'name' => 'Promotion Website', 'code' => 'PROMO-WEB', 'type' => 'website', 'is_online' => true, 'is_active' => true]);
        return compact('category', 'brand', 'unit', 'product', 'channel');
    }

    /** @param array<string, mixed> $attributes @param array<string, mixed> $action */
    private function rule(User $user, array $attributes, array $action): PromotionRule
    {
        $identifier = (string) str()->uuid();
        $rule = PromotionRule::create($attributes + ['company_id' => $user->company_id, 'name' => 'Rule '.$identifier, 'slug' => 'rule-'.$identifier, 'promotion_type' => PromotionType::PercentageDiscount->value, 'discount_type' => 'percentage', 'priority' => 100, 'stackable' => false, 'exclusive' => false, 'requires_coupon' => false, 'auto_apply' => true, 'status' => 'active', 'is_active' => true, 'created_by' => $user->id]);
        $rule->actions()->create(['company_id' => $user->company_id, 'applies_to_same_product' => true] + $action);
        return $rule;
    }

    /** @param array<string, mixed> $fixtures @param array<string, mixed> $overrides @return array<string, mixed> */
    private function rulePayload(array $fixtures, array $overrides = []): array
    {
        return $overrides + ['name' => 'Promotion rule', 'slug' => 'promotion-rule', 'promotion_type' => 'percentage_discount', 'discount_type' => 'percentage', 'status' => 'draft', 'priority' => 100, 'auto_apply' => '1', 'actions' => [['action_type' => 'percentage_off', 'discount_percentage' => 10, 'applies_to_same_product' => '1']]];
    }

    /** @param array<string, mixed> $fixtures @return array<string, mixed> */
    private function cart(array $fixtures, float $quantity): array
    {
        return ['branch_id' => $fixtures['product']->branch_id, 'sales_channel_id' => $fixtures['channel']->id, 'items' => [['product_id' => $fixtures['product']->id, 'product_name' => $fixtures['product']->name, 'category_id' => $fixtures['category']->id, 'brand_id' => $fixtures['brand']->id, 'quantity' => $quantity, 'unit_price' => 100]], 'bill_subtotal' => $quantity * 100];
    }
}

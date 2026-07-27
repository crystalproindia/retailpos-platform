<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\SaasPlan;
use App\Models\SaasTenantOnboarding;
use App\Models\StoreSetupWizard;
use App\Models\User;
use App\Services\Saas\StoreSetupWizardService;
use App\Services\Saas\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreSetupWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_provisioned_administrator_is_redirected_to_a_resumable_wizard(): void
    {
        [$company, $user] = $this->tenant(true);
        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('onboarding.store-setup.show'));
        $this->actingAs($user)->get(route('onboarding.store-setup.show'))->assertOk()->assertSee('Set up your store, your way');
        $this->actingAs($user)->post(route('onboarding.store-setup.start'))->assertRedirect(route('onboarding.store-setup.show'));
        $this->assertDatabaseHas('store_setup_wizards', ['company_id' => $company->id, 'current_step' => 1]);
    }

    public function test_established_tenant_is_not_forced_into_store_setup(): void
    {
        [, $user] = $this->tenant(false);
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
    }

    public function test_answers_persist_and_recommendations_are_deterministic(): void
    {
        [, $user] = $this->tenant(true, 'grocery_supermarket');
        $this->actingAs($user)->post(route('onboarding.store-setup.start'));
        $this->actingAs($user)->post(route('onboarding.store-setup.save'), ['step' => 1, 'subtypes' => ['grocery']])->assertRedirect();
        $this->actingAs($user)->post(route('onboarding.store-setup.save'), ['step' => 2, 'product_volume' => '50_250'])->assertRedirect();
        $wizard = StoreSetupWizard::firstOrFail();
        $this->assertSame(['grocery'], $wizard->answers['subtypes']);
        $this->assertSame('csv_template', $wizard->recommendations['product_entry']['method']);
        $this->assertSame('Grocery', $wizard->recommendations['categories'][0]['name']);
    }

    public function test_invalid_subtype_and_gst_format_are_rejected(): void
    {
        [, $user] = $this->tenant(true);
        $this->actingAs($user)->post(route('onboarding.store-setup.start'));
        $this->actingAs($user)->from(route('onboarding.store-setup.show'))->post(route('onboarding.store-setup.save'), ['step' => 1, 'subtypes' => ['unsupported']])->assertRedirect(route('onboarding.store-setup.show'))->assertSessionHasErrors('subtypes');
        $this->actingAs($user)->from(route('onboarding.store-setup.show'))->post(route('onboarding.store-setup.save'), ['step' => 3, 'registered' => 1, 'gstin' => 'bad'])->assertRedirect(route('onboarding.store-setup.show'))->assertSessionHasErrors('gstin');
    }

    public function test_apply_creates_only_selected_categories_and_is_idempotent(): void
    {
        [$company, $user] = $this->tenant(true, 'fashion_apparel');
        $wizard = app(StoreSetupWizardService::class)->wizard($user);
        $wizard->update(['current_step' => 6, 'answers' => ['industry' => 'fashion_apparel', 'subtypes' => ['mens_clothing'], 'product_volume' => 'under_50', 'tax' => ['registered' => false, 'rates' => []], 'scanner' => ['choice' => 'manual_search'], 'printer' => ['type' => 'a4'], 'import' => ['choice' => 'manual']], 'recommendations' => app(\App\Services\Saas\StoreSetupRecommendationService::class)->make($company, ['industry' => 'fashion_apparel', 'printer' => ['type' => 'a4'], 'scanner' => ['choice' => 'manual_search'], 'tax' => ['registered' => false, 'rates' => []], 'product_volume' => 'under_50'])]);
        $payload = ['categories' => ['Men', 'Women'], 'apply_tax' => 1, 'apply_template' => 1, 'apply_barcode' => 1];
        $this->actingAs($user)->post(route('onboarding.store-setup.apply'), $payload)->assertRedirect(route('onboarding.store-setup.complete'));
        $this->actingAs($user)->post(route('onboarding.store-setup.apply'), $payload)->assertRedirect(route('onboarding.store-setup.complete'));
        $this->assertDatabaseCount('inventory_categories', 2);
        $this->assertDatabaseHas('store_setup_wizards', ['company_id' => $company->id, 'status' => 'completed']);
    }

    public function test_confirmed_gst_and_scanner_preferences_apply_without_storing_sample_scan_data(): void
    {
        [$company, $user] = $this->tenant(true, 'grocery_supermarket');
        $wizard = app(StoreSetupWizardService::class)->wizard($user);
        $answers = ['industry' => 'grocery_supermarket', 'subtypes' => ['grocery'], 'product_volume' => '50_250', 'tax' => ['registered' => true, 'gstin' => '27ABCDE1234F1Z5', 'state_code' => '27', 'state_name' => 'Maharashtra', 'rates' => ['5', '18']], 'scanner' => ['choice' => 'already_have', 'format' => 'code128', 'generate_missing' => true], 'printer' => ['type' => 'thermal'], 'import' => ['choice' => 'csv_template']];
        $wizard->update(['current_step' => 6, 'answers' => $answers, 'recommendations' => app(\App\Services\Saas\StoreSetupRecommendationService::class)->make($company, $answers)]);
        $this->actingAs($user)->post(route('onboarding.store-setup.apply'), ['categories' => ['Grocery'], 'apply_tax' => 1, 'apply_barcode' => 1])->assertRedirect();
        $this->assertDatabaseHas('gst_settings', ['company_id' => $company->id, 'gstin' => '27ABCDE1234F1Z5']);
        $this->assertDatabaseHas('inventory_tax_rates', ['company_id' => $company->id, 'rate' => 5]);
        $this->assertDatabaseHas('barcode_label_templates', ['company_id' => $company->id, 'name' => 'Store Setup Barcode Label']);
        $this->assertDatabaseMissing('products', ['company_id' => $company->id, 'barcode' => 'sample-scan']);
    }

    public function test_sales_user_cannot_manage_tenant_setup_and_product_template_is_safe_csv(): void
    {
        [$company] = $this->tenant(true);
        $sales = User::factory()->for($company)->create(['role' => UserRole::Sales]);
        $this->actingAs($sales)->get(route('onboarding.store-setup.show'))->assertForbidden();
        [, $admin] = $this->tenant(true);
        $response = $this->actingAs($admin)->get(route('onboarding.store-setup.template'))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Product name', $response->streamedContent());
    }

    /** @return array{Company, User} */
    private function tenant(bool $provisioned, string $industry = 'general_retail'): array
    {
        $company = Company::factory()->create(['industry' => $industry]);
        $branch = Branch::factory()->for($company)->create();
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Administrator]);
        $plan = SaasPlan::query()->firstOrCreate(['code' => config('saas.free365_plan_code')], ['name' => 'Free 365', 'status' => 'active', 'billing_interval' => 'yearly', 'currency' => 'INR']);
        foreach (['dashboard.basic', 'pos.billing', 'sales.invoices', 'inventory.basic', 'customers.basic'] as $feature) $plan->features()->updateOrCreate(['feature_key' => $feature], ['is_enabled' => true]);
        app(SubscriptionService::class)->create($company, $plan, $user);
        if ($provisioned) SaasTenantOnboarding::create(['idempotency_key' => (string) Str::uuid(), 'company_id' => $company->id, 'saas_plan_id' => $plan->id, 'status' => 'completed', 'current_stage' => 'complete', 'signup_source' => 'public', 'payload' => [], 'completed_at' => now()]);
        return [$company, $user];
    }
}

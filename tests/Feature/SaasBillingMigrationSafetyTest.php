<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\SaasPlan;
use App\Models\SaasSubscriptionInvoice;
use App\Models\User;
use App\Services\Saas\SaasSubscriptionInvoiceService;
use App\Services\Saas\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SaasBillingMigrationSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_8b_migrations_restore_missing_objects_without_recreating_existing_billing_data(): void
    {
        $invoice = $this->invoice();
        $billingMigration = require database_path('migrations/2026_07_23_020000_create_saas_billing_foundation.php');
        $onlineMigration = require database_path('migrations/2026_07_23_030000_create_saas_online_billing_foundation.php');

        Schema::table('saas_subscription_invoices', function ($table): void {
            $table->dropForeign(['issued_by']);
        });
        Schema::dropIfExists('saas_billing_refunds');
        Schema::dropIfExists('saas_billing_payments');
        Schema::dropIfExists('saas_subscription_invoice_items');
        Schema::dropIfExists('saas_billing_webhook_events');
        Schema::dropIfExists('saas_billing_checkout_sessions');

        $billingMigration->up();
        $onlineMigration->up();
        $billingMigration->up();
        $onlineMigration->up();

        $this->assertSame(1, SaasSubscriptionInvoice::query()->whereKey($invoice->id)->count());
        $this->assertTrue(Schema::hasForeignKey('saas_subscription_invoices', ['issued_by']));

        foreach ([
            'saas_subscription_invoices',
            'saas_subscription_invoice_items',
            'saas_billing_payments',
            'saas_billing_refunds',
            'saas_billing_checkout_sessions',
            'saas_billing_webhook_events',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }

        $this->assertTrue(Schema::hasIndex('saas_subscription_invoices', ['company_id', 'invoice_number'], 'unique'));
        $this->assertTrue(Schema::hasIndex('saas_billing_payments', ['provider', 'provider_payment_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('saas_billing_webhook_events', ['provider', 'provider_event_id'], 'unique'));
        $this->assertCount(6, Schema::getForeignKeys('saas_subscription_invoices'));
        $this->assertCount(5, Schema::getForeignKeys('saas_billing_payments'));
        $this->assertCount(2, Schema::getForeignKeys('saas_billing_webhook_events'));
    }

    public function test_phase_8b_explicit_identifiers_are_mysql_safe(): void
    {
        $identifiers = [
            'saas_inv_company_fk', 'saas_inv_subscription_fk', 'saas_inv_plan_fk', 'saas_inv_created_by_fk', 'saas_inv_issued_by_fk', 'saas_inv_voided_by_fk',
            'saas_inv_items_invoice_fk', 'saas_pay_company_fk', 'saas_pay_invoice_fk', 'saas_pay_subscription_fk', 'saas_pay_recorded_by_fk', 'saas_pay_reversed_by_fk',
            'saas_ref_company_fk', 'saas_ref_payment_fk', 'saas_ref_invoice_fk', 'saas_ref_requested_by_fk', 'saas_ref_approved_by_fk',
            'saas_checkout_company_fk', 'saas_checkout_invoice_fk', 'saas_checkout_subscription_fk', 'saas_checkout_integration_fk',
            'saas_webhook_integration_fk', 'saas_webhook_company_fk', 'saas_webhook_provider_event_uq',
        ];
        $source = file_get_contents(database_path('migrations/2026_07_23_020000_create_saas_billing_foundation.php'))
            .file_get_contents(database_path('migrations/2026_07_23_030000_create_saas_online_billing_foundation.php'));

        foreach ($identifiers as $identifier) {
            $this->assertLessThanOrEqual(64, strlen($identifier), $identifier.' exceeds MySQL\'s identifier limit.');
            $this->assertStringContainsString("'{$identifier}'", $source);
        }

        $this->assertStringNotContainsString('saas_subscription_invoice_items_saas_subscription_invoice_id_foreign', $source);
    }

    private function invoice(): SaasSubscriptionInvoice
    {
        $company = Company::factory()->create(['currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create();
        $administrator = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Administrator]);
        $plan = SaasPlan::create([
            'name' => 'Migration safety',
            'code' => 'migration-safety-'.str()->random(8),
            'status' => 'active',
            'billing_interval' => 'monthly',
            'currency' => 'INR',
            'base_price' => 1000,
            'tax_percentage' => 0,
        ]);
        $subscription = app(SubscriptionService::class)->create($company, $plan, $administrator);

        return app(SaasSubscriptionInvoiceService::class)->create($subscription, $administrator);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CustomerVendorFinanceMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_schema_is_additive_indexed_and_uses_explicit_mysql_safe_names(): void
    {
        $this->assertTrue(Schema::hasColumns('crm_customers', ['credit_limit', 'credit_terms_days']));
        $this->assertTrue(Schema::hasColumns('crm_invoice_payments', ['customer_id', 'allocated_amount', 'unallocated_amount']));
        $this->assertTrue(Schema::hasTable('crm_invoice_payment_allocations'));
        $this->assertTrue(Schema::hasTable('crm_customer_credit_allocations'));
        $this->assertTrue(Schema::hasTable('finance_reconciliations'));
        $source = file_get_contents(database_path('migrations/2026_08_28_010000_create_customer_vendor_finance_foundation.php'));
        $this->assertStringContainsString('crm_pay_alloc_payment_invoice_idx', $source);
        $this->assertStringContainsString('finance_recon_company_payment_uq', $source);
        $this->assertStringNotContainsString('float(', $source);
    }
}

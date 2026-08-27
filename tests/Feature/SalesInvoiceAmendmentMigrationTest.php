<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesInvoiceAmendmentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_amendment_schema_is_additive_traceable_and_mysql_safe(): void
    {
        $this->assertTrue(Schema::hasTable('crm_invoice_amendments'));
        $this->assertTrue(Schema::hasTable('crm_invoice_amendment_items'));
        $this->assertTrue(Schema::hasColumns('crm_invoices', ['amendment_version', 'last_amended_at']));
        $this->assertTrue(Schema::hasColumn('crm_invoice_items', 'amendment_id'));
        $this->assertTrue(Schema::hasColumn('stock_movements', 'crm_invoice_item_id'));
        $this->assertTrue(Schema::hasIndex('crm_invoice_amendments', ['invoice_id', 'version_to'], 'unique'));
        $this->assertTrue(Schema::hasIndex('crm_invoice_amendments', ['company_id', 'idempotency_key'], 'unique'));
        $this->assertTrue(Schema::hasIndex('stock_movements', ['crm_invoice_item_id'], 'unique'));

        $source = file_get_contents(database_path('migrations/2026_08_29_010000_create_crm_invoice_amendments.php'));
        foreach (['crm_inv_amend_invoice_version_uq', 'crm_inv_amend_company_idem_uq', 'crm_inv_amend_scope_final_idx', 'crm_inv_item_amendment_idx', 'crm_inv_amend_item_line_uq', 'stock_move_crm_inv_item_uq'] as $identifier) {
            $this->assertLessThanOrEqual(64, strlen($identifier));
            $this->assertStringContainsString("'{$identifier}'", $source);
        }
    }

    public function test_migration_can_resume_after_all_additive_steps_already_exist(): void
    {
        $migration = require database_path('migrations/2026_08_29_010000_create_crm_invoice_amendments.php');

        $migration->up();

        $this->assertTrue(Schema::hasTable('crm_invoice_amendments'));
        $this->assertTrue(Schema::hasTable('crm_invoice_amendment_items'));
        $this->assertTrue(Schema::hasColumns('crm_invoices', ['amendment_version', 'last_amended_at']));
        $this->assertTrue(Schema::hasColumn('crm_invoice_items', 'amendment_id'));
        $this->assertTrue(Schema::hasColumn('stock_movements', 'crm_invoice_item_id'));
    }
}

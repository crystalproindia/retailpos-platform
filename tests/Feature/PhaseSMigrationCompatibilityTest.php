<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhaseSMigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_s_migration_resumes_after_the_production_partial_ddl_state(): void
    {
        $migrationPath = database_path('migrations/2026_08_14_010000_add_smart_purchase_automation_controls.php');
        $migrationName = pathinfo($migrationPath, PATHINFO_FILENAME);
        $migration = require $migrationPath;

        $migration->down();

        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();
        $administrator = User::factory()->for($company)->create([
            'branch_id' => $branch->id,
            'role' => UserRole::Administrator,
        ]);

        $requestId = DB::table('purchase_requests')->insertGetId([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'request_number' => 'PR-PARTIAL-001',
            'source_type' => 'manual',
            'status' => 'submitted',
            'priority' => 'normal',
            'requested_by' => $administrator->id,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('purchase_requests', function (Blueprint $table): void {
            $table->dropForeign(['cancelled_by']);
        });
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropIndex('purch_order_company_warehouse_status_idx');
        });

        Schema::create('goods_receipt_item_serials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_item_id');
            $table->string('serial_number', 160);
            $table->timestamps();
            $table->unique(['goods_receipt_item_id', 'serial_number'], 'grn_item_serial_unique');
            $table->foreign(
                'goods_receipt_item_id',
                'goods_receipt_item_serials_goods_receipt_item_id_foreign',
            )->references('id')->on('goods_receipt_items')->cascadeOnDelete();
        });

        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('purchase_requests', [
            'id' => $requestId,
            'request_number' => 'PR-PARTIAL-001',
            'status' => 'submitted',
        ]);
        $this->assertTrue(Schema::hasTable('goods_receipt_item_serials'));
        $this->assertTrue(Schema::hasTable('purchase_invoice_match_exceptions'));
        $this->assertTrue(Schema::hasForeignKey('purchase_requests', ['cancelled_by']));
        $this->assertTrue(Schema::hasForeignKey('goods_receipt_item_serials', ['goods_receipt_item_id']));
        $this->assertTrue(Schema::hasForeignKey('purchase_invoice_match_exceptions', ['purchase_invoice_item_id']));
        $this->assertTrue(Schema::hasIndex('purchase_orders', 'purch_order_company_warehouse_status_idx'));

        $invoiceItemForeign = collect(Schema::getForeignKeys('purchase_invoice_match_exceptions'))
            ->firstWhere('columns', ['purchase_invoice_item_id']);

        $this->assertSame('purchase_invoice_items', $invoiceItemForeign['foreign_table'] ?? null);
        $this->assertSame(['id'], $invoiceItemForeign['foreign_columns'] ?? null);
        $this->assertSame('set null', strtolower($invoiceItemForeign['on_delete'] ?? ''));
        $this->assertCount(1, collect(Schema::getIndexes('purchase_orders'))->filter(
            fn (array $index): bool => ($index['columns'] ?? []) === ['company_id', 'warehouse_id', 'status'],
        ));

        DB::table('migrations')->where('migration', $migrationName)->delete();

        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertSame(1, DB::table('migrations')->where('migration', $migrationName)->count());
        $this->assertDatabaseHas('purchase_requests', ['id' => $requestId]);
    }

    public function test_phase_s_explicit_identifiers_are_mysql_safe(): void
    {
        $identifiers = [
            'phase_s_pr_cancelled_by_fk',
            'phase_s_grn_posted_by_fk',
            'phase_s_grni_batch_fk',
            'phase_s_grnis_item_fk',
            'phase_s_pi_match_reviewer_fk',
            'phase_s_pime_company_fk',
            'phase_s_pime_invoice_fk',
            'phase_s_pime_order_fk',
            'phase_s_pime_grn_item_fk',
            'pime_invoice_item_fk',
            'phase_s_pime_resolved_by_fk',
            'purch_req_company_branch_status_idx',
            'purch_order_company_warehouse_status_idx',
            'grn_company_idempotency_uq',
            'grn_company_warehouse_status_idx',
            'grn_item_serial_unique',
            'purch_invoice_company_match_status_idx',
            'purch_inv_match_company_status_idx',
            'purch_inv_match_invoice_status_idx',
        ];
        $source = file_get_contents(database_path('migrations/2026_08_14_010000_add_smart_purchase_automation_controls.php'));

        foreach ($identifiers as $identifier) {
            $this->assertLessThanOrEqual(64, strlen($identifier), $identifier.' exceeds MySQL\'s identifier limit.');
            $this->assertStringContainsString("'{$identifier}'", $source);
        }

        $this->assertStringNotContainsString(
            'purchase_invoice_match_exceptions_purchase_invoice_item_id_foreign',
            $source,
        );
    }
}

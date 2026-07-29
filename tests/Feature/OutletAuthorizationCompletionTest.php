<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\Crm\LeadStageType;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\BranchUserAssignment;
use App\Models\Company;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\StockLocation;
use App\Models\User;
use App\Repositories\Crm\InvoiceRepository;
use App\Services\Crm\CrmExecutiveReportService;
use App\Services\Crm\InvoiceService;
use App\Services\Inventory\StockService;
use App\Services\Outlets\OutletAccessService;
use App\Services\Pos\PosCheckoutService;
use App\Services\Purchases\PurchaseReturnService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OutletAuthorizationCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_invoices_and_payments_are_rejected_for_an_unassigned_outlet(): void
    {
        [$manager, $primary, $secondary] = $this->managerWithTwoOutlets();
        $administrator = User::factory()->for($manager->company)->create(['branch_id' => $primary->id, 'role' => UserRole::Administrator]);
        app(OutletAccessService::class)->switch($administrator, $secondary->id);

        $invoice = app(InvoiceService::class)->create($administrator, $this->invoicePayload());
        app(InvoiceService::class)->issue($invoice, $administrator);

        $this->assertSame($secondary->id, $invoice->branch_id);
        $this->actingAs($manager)->get('/sales/invoices/'.$invoice->id)->assertNotFound();

        try {
            app(InvoiceService::class)->recordPayment($invoice, $manager, [
                'amount' => 100,
                'currency' => 'INR',
                'payment_date' => today()->toDateString(),
                'payment_method' => 'cash',
            ]);
            $this->fail('A payment was recorded against an outlet the manager cannot access.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('outlet', $exception->errors());
        }

        $this->expectException(ModelNotFoundException::class);
        app(InvoiceRepository::class)->find($manager, $invoice->id);
    }

    public function test_pos_adjustments_and_purchase_returns_reject_an_unassigned_outlet(): void
    {
        [$manager, , $secondary] = $this->managerWithTwoOutlets();
        $warehouse = Warehouse::create([
            'company_id' => $manager->company_id,
            'branch_id' => $secondary->id,
            'name' => 'Secondary Warehouse',
            'code' => 'SECONDARY-WH',
            'type' => 'store',
            'country' => 'India',
            'is_active' => true,
        ]);

        $this->assertOutletValidation(fn () => app(PosCheckoutService::class)->hold($manager, [
            'branch_id' => $secondary->id,
            'items' => [],
        ]), 'branch_id');

        $this->assertOutletValidation(fn () => app(StockService::class)->createAdjustment($manager, [
            'warehouse_id' => $warehouse->id,
            'reason' => 'Outlet authorization regression',
            'items' => [],
        ]), 'warehouse_id');

        $this->assertOutletValidation(fn () => app(PurchaseReturnService::class)->create($manager, [
            'warehouse_id' => $warehouse->id,
            'reason' => 'Outlet authorization regression',
            'items' => [],
        ]), 'warehouse_id');
    }

    public function test_crm_reports_default_to_the_selected_outlet_and_reserve_all_outlets_for_administrators(): void
    {
        [$manager, $primary, $secondary] = $this->managerWithTwoOutlets();
        $status = CrmLeadStatus::create(['company_id' => $manager->company_id, 'name' => 'New', 'slug' => 'new', 'stage_type' => LeadStageType::New, 'is_active' => true]);
        $source = CrmLeadSource::create(['company_id' => $manager->company_id, 'name' => 'Website', 'slug' => 'website', 'is_active' => true]);
        CrmLead::create(['company_id' => $manager->company_id, 'branch_id' => $primary->id, 'source_id' => $source->id, 'status_id' => $status->id, 'title' => 'Primary outlet lead']);
        CrmLead::create(['company_id' => $manager->company_id, 'branch_id' => $secondary->id, 'source_id' => $source->id, 'status_id' => $status->id, 'title' => 'Secondary outlet lead']);

        app(OutletAccessService::class)->switch($manager, $primary->id);
        $this->assertSame(1, app(CrmExecutiveReportService::class)->dashboard($manager)['areas']['sales']['metrics']['total_leads']);
        $this->assertOutletValidation(fn () => app(CrmExecutiveReportService::class)->dashboard($manager, ['outlet_id' => 'all']), 'outlet_id');

        $administrator = User::factory()->for($manager->company)->create(['branch_id' => $primary->id, 'role' => UserRole::Administrator]);
        $this->assertSame(2, app(CrmExecutiveReportService::class)->dashboard($administrator, ['outlet_id' => 'all'])['areas']['sales']['metrics']['total_leads']);
    }

    public function test_stock_records_reject_locations_from_another_outlet(): void
    {
        [$manager, $primary, $secondary] = $this->managerWithTwoOutlets();
        $primaryWarehouse = Warehouse::create([
            'company_id' => $manager->company_id,
            'branch_id' => $primary->id,
            'name' => 'Primary Warehouse',
            'code' => 'PRIMARY-WH',
            'type' => 'store',
            'country' => 'India',
            'is_active' => true,
        ]);
        $secondaryWarehouse = Warehouse::create([
            'company_id' => $manager->company_id,
            'branch_id' => $secondary->id,
            'name' => 'Secondary Warehouse',
            'code' => 'SECONDARY-WH',
            'type' => 'store',
            'country' => 'India',
            'is_active' => true,
        ]);
        $secondaryLocation = StockLocation::create([
            'company_id' => $manager->company_id,
            'warehouse_id' => $secondaryWarehouse->id,
            'name' => 'Secondary Bin',
            'code' => 'SECONDARY-BIN',
            'is_active' => true,
        ]);

        $this->assertOutletValidation(fn () => app(StockService::class)->createAdjustment($manager, [
            'warehouse_id' => $primaryWarehouse->id,
            'reason' => 'Cross-outlet location authorization regression',
            'items' => [[
                'product_id' => 999,
                'stock_location_id' => $secondaryLocation->id,
                'adjusted_quantity' => 1,
            ]],
        ]), 'stock_location_id');

        $this->assertOutletValidation(fn () => app(PurchaseReturnService::class)->create($manager, [
            'warehouse_id' => $primaryWarehouse->id,
            'reason' => 'Cross-outlet location authorization regression',
            'items' => [[
                'product_id' => 999,
                'stock_location_id' => $secondaryLocation->id,
                'quantity' => 1,
                'unit_cost' => 1,
            ]],
        ]), 'items');
    }

    /** @return array{User, Branch, Branch} */
    private function managerWithTwoOutlets(): array
    {
        $company = Company::factory()->create();
        $primary = Branch::factory()->for($company)->create(['code' => 'MAIN', 'is_primary' => true]);
        $secondary = Branch::factory()->for($company)->create(['code' => 'SECONDARY']);
        $manager = User::factory()->for($company)->create(['branch_id' => $primary->id, 'role' => UserRole::Manager]);
        BranchUserAssignment::create(['company_id' => $company->id, 'branch_id' => $primary->id, 'user_id' => $manager->id, 'is_default' => true, 'is_active' => true, 'assigned_by' => $manager->id]);

        return [$manager, $primary, $secondary];
    }

    /** @return array<string, mixed> */
    private function invoicePayload(): array
    {
        return [
            'billing_name' => 'Outlet customer',
            'currency' => 'INR',
            'items' => [['name' => 'Outlet implementation', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]],
        ];
    }

    private function assertOutletValidation(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('An operation was allowed for an unassigned outlet.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }
}

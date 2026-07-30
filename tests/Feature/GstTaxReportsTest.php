<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class GstTaxReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_gst_report_reconciles_intra_state_inter_state_and_incomplete_invoice_snapshots(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'GST Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $intraState = $this->reportInvoice($company, $outlet, 'GST-INTRA', '118.00', '118.00');
        $interState = $this->reportInvoice($company, $outlet, 'GST-INTER', '118.00', '118.00');
        $incomplete = $this->reportInvoice($company, $outlet, 'GST-MISSING', '50.00', '50.00');
        $cancelled = $this->reportInvoice($company, $outlet, 'GST-CANCELLED', '118.00', '118.00', 'cancelled');

        $intraState->update(['taxable_total' => '100.00', 'cgst_total' => '9.00', 'sgst_total' => '9.00', 'tax_total' => '18.00', 'place_of_supply_state_code' => 'KA']);
        $interState->update(['taxable_total' => '100.00', 'igst_total' => '18.00', 'tax_total' => '18.00', 'place_of_supply_state_code' => 'MH']);
        $incomplete->update(['taxable_total' => '50.00']);
        $cancelled->update(['taxable_total' => '100.00', 'cgst_total' => '9.00', 'sgst_total' => '9.00', 'tax_total' => '18.00', 'place_of_supply_state_code' => 'KA']);

        $report = $this->reportFor($administrator, 'gst');

        $this->assertSame(25000, $report['detail']['taxable_sales']);
        $this->assertSame(900, $report['detail']['cgst']);
        $this->assertSame(900, $report['detail']['sgst']);
        $this->assertSame(1800, $report['detail']['igst']);
        $this->assertSame(1, $report['detail']['incomplete_count']);
        $this->assertEqualsCanonicalizing(['GST-INTRA', 'GST-INTER', 'GST-MISSING'], array_column($report['detail']['rows'], 'invoice'));
    }

    public function test_gst_detail_and_csv_preserve_outlet_scope(): void
    {
        $company = Company::factory()->create();
        $assigned = $this->reportBranch($company, 'Assigned GST Outlet');
        $unassigned = $this->reportBranch($company, 'Unassigned GST Outlet');
        $administrator = $this->reportUser($company, $assigned);
        $manager = $this->reportUser($company, $assigned, UserRole::Manager);
        $assignedInvoice = $this->reportInvoice($company, $assigned, 'GST-CSV-ASSIGNED', '10.00', '10.00');
        $unassignedInvoice = $this->reportInvoice($company, $unassigned, 'GST-CSV-HIDDEN', '20.00', '20.00');
        $assignedInvoice->update(['taxable_total' => '10.00', 'place_of_supply_state_code' => 'KA']);
        $unassignedInvoice->update(['taxable_total' => '20.00', 'place_of_supply_state_code' => 'KA']);

        $this->actingAs($manager)->get('/reports/gst?outlet_id='.$unassigned->id)->assertSessionHasErrors('outlet_id');
        $detail = $this->actingAs($administrator)->get('/reports/gst?outlet_id='.$assigned->id);
        $csv = $this->actingAs($administrator)->get('/reports/gst/export?outlet_id='.$assigned->id);

        $detail->assertOk()->assertSee('GST-CSV-ASSIGNED')->assertDontSee('GST-CSV-HIDDEN');
        $csv->assertOk();
        $this->assertStringContainsString('GST-CSV-ASSIGNED', $csv->streamedContent());
        $this->assertStringNotContainsString('GST-CSV-HIDDEN', $csv->streamedContent());
        $this->assertStringContainsString('Outlet scope', $csv->streamedContent());
    }
}

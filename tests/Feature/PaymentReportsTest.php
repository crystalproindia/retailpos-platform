<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class PaymentReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_authorized_partial_and_final_crm_payments_are_included_while_reversals_are_excluded(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Payment Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $invoice = $this->reportInvoice($company, $outlet, 'CRM-PAYMENT-ONE', '100.00', '0.00', 'paid');

        $this->reportPayment($company, $outlet, $invoice, 'PAY-PARTIAL', '20.25');
        $this->reportPayment($company, $outlet, $invoice, 'PAY-FINAL', '79.75', 'cleared');
        $this->reportPayment($company, $outlet, $invoice, 'PAY-REVERSED', '50.00', 'reversed');

        $report = $this->reportFor($administrator, 'payments');

        $this->assertSame(10000, $report['detail']['received']);
        $this->assertSame(2, $report['detail']['count']);
        $this->assertEqualsCanonicalizing(['PAY-PARTIAL', 'PAY-FINAL'], array_column($report['detail']['rows'], 'reference'));
    }

    public function test_legacy_branchless_payments_derive_their_scope_and_visible_outlet_from_the_source_invoice(): void
    {
        $company = Company::factory()->create();
        $first = $this->reportBranch($company, 'Legacy First Outlet');
        $second = $this->reportBranch($company, 'Legacy Second Outlet');
        $administrator = $this->reportUser($company, $first);
        $manager = $this->reportUser($company, $first, UserRole::Manager);
        $firstInvoice = $this->reportInvoice($company, $first, 'CRM-LEGACY-FIRST', '25.00', '0.00', 'paid');
        $secondInvoice = $this->reportInvoice($company, $second, 'CRM-LEGACY-SECOND', '35.00', '0.00', 'paid');

        $this->reportPayment($company, null, $firstInvoice, 'PAY-LEGACY-FIRST', '25.00');
        $this->reportPayment($company, null, $secondInvoice, 'PAY-LEGACY-SECOND', '35.00');

        $managerReport = $this->reportFor($manager, 'payments');
        $administratorReport = $this->reportFor($administrator, 'payments', ['outlet_id' => 'all']);

        $this->assertSame(2500, $managerReport['detail']['received']);
        $this->assertSame(['Legacy First Outlet'], array_unique(array_column($managerReport['detail']['rows'], 'outlet')));
        $this->assertSame(6000, $administratorReport['detail']['received']);
    }

    public function test_payment_reports_reject_cross_tenant_and_unassigned_outlet_filters(): void
    {
        $company = Company::factory()->create();
        $assigned = $this->reportBranch($company, 'Assigned Payment Outlet');
        $unassigned = $this->reportBranch($company, 'Unassigned Payment Outlet');
        $manager = $this->reportUser($company, $assigned, UserRole::Manager);
        $otherCompany = Company::factory()->create();
        $otherOutlet = $this->reportBranch($otherCompany, 'Other Tenant Payment Outlet');

        $this->actingAs($manager)->get('/reports/payments?outlet_id='.$unassigned->id)->assertSessionHasErrors('outlet_id');
        $this->actingAs($manager)->get('/reports/payments?outlet_id='.$otherOutlet->id)->assertSessionHasErrors('outlet_id');
    }

    public function test_payment_csv_matches_the_authorized_detail_rows(): void
    {
        $company = Company::factory()->create();
        $outlet = $this->reportBranch($company, 'Payment CSV Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $invoice = $this->reportInvoice($company, $outlet, 'CRM-PAYMENT-CSV', '12.34', '0.00', 'paid');
        $this->reportPayment($company, $outlet, $invoice, 'PAY-CSV', '12.34');
        $this->reportPayment($company, $outlet, $invoice, 'PAY-CSV-REVERSED', '12.34', 'reversed');

        $detail = $this->actingAs($administrator)->get('/reports/payments?outlet_id='.$outlet->id);
        $csv = $this->actingAs($administrator)->get('/reports/payments/export?outlet_id='.$outlet->id);

        $detail->assertOk()->assertSee('PAY-CSV')->assertDontSee('PAY-CSV-REVERSED');
        $csv->assertOk();
        $this->assertStringContainsString('PAY-CSV', $csv->streamedContent());
        $this->assertStringNotContainsString('PAY-CSV-REVERSED', $csv->streamedContent());
        $this->assertStringContainsString('12.34', $csv->streamedContent());
    }
}

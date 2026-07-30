<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsReportingData;
use Tests\TestCase;

class OutstandingAgingReportsTest extends TestCase
{
    use RefreshDatabase;
    use BuildsReportingData;

    public function test_outstanding_rows_use_source_balances_and_exclude_paid_and_cancelled_invoices(): void
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata']);
        $outlet = $this->reportBranch($company, 'Outstanding Outlet');
        $administrator = $this->reportUser($company, $outlet);

        $this->reportInvoice($company, $outlet, 'INV-OUTSTANDING', '100.00', '60.25', 'partially_paid', today(), today()->addDays(7));
        $this->reportInvoice($company, $outlet, 'INV-PAID', '40.00', '0.00', 'paid', today(), today()->subDay());
        $this->reportInvoice($company, $outlet, 'INV-CANCELLED', '75.00', '75.00', 'cancelled', today(), today()->subDay());

        $report = $this->reportFor($administrator, 'outstanding');

        $this->assertSame(6025, $report['detail']['outstanding']);
        $this->assertSame(['INV-OUTSTANDING'], array_column($report['detail']['rows'], 'invoice'));
        $this->assertSame(6025, collect($report['detail']['aging'])->firstWhere('bucket', 'Current')['outstanding']);
    }

    public function test_aging_buckets_respect_the_company_timezone_and_reversed_payments_leave_the_source_balance_outstanding(): void
    {
        $company = Company::factory()->create(['timezone' => 'Asia/Kolkata']);
        $outlet = $this->reportBranch($company, 'Aging Outlet');
        $administrator = $this->reportUser($company, $outlet);
        $asOf = now('Asia/Kolkata')->startOfDay();

        $this->reportInvoice($company, $outlet, 'INV-CURRENT', '1.00', '1.00', 'issued', $asOf, $asOf->copy()->addDay());
        $this->reportInvoice($company, $outlet, 'INV-1-30', '2.00', '2.00', 'overdue', $asOf, $asOf->copy()->subDays(30));
        $this->reportInvoice($company, $outlet, 'INV-31-60', '3.00', '3.00', 'overdue', $asOf, $asOf->copy()->subDays(31));
        $this->reportInvoice($company, $outlet, 'INV-61-90', '4.00', '4.00', 'overdue', $asOf, $asOf->copy()->subDays(61));
        $reversedInvoice = $this->reportInvoice($company, $outlet, 'INV-91-PLUS', '5.00', '5.00', 'overdue', $asOf, $asOf->copy()->subDays(91));
        $this->reportPayment($company, $outlet, $reversedInvoice, 'PAY-REVERSED-OUTSTANDING', '5.00', 'reversed', $asOf);

        $report = $this->reportFor($administrator, 'outstanding', ['date_from' => $asOf->toDateString(), 'date_to' => $asOf->toDateString()]);
        $aging = collect($report['detail']['aging'])->mapWithKeys(fn (array $row) => [$row['bucket'] => $row['outstanding']])->all();

        $this->assertSame(1500, $report['detail']['outstanding']);
        $this->assertSame(['Current' => 100, '1-30 days' => 200, '31-60 days' => 300, '61-90 days' => 400, '91+ days' => 500], $aging);
        $this->assertContains('INV-91-PLUS', array_column($report['detail']['rows'], 'invoice'));
    }

    public function test_outstanding_reports_preserve_outlet_authorization_and_csv_parity(): void
    {
        $company = Company::factory()->create();
        $assigned = $this->reportBranch($company, 'Assigned Outstanding Outlet');
        $unassigned = $this->reportBranch($company, 'Unassigned Outstanding Outlet');
        $administrator = $this->reportUser($company, $assigned);
        $manager = $this->reportUser($company, $assigned, UserRole::Manager);
        $this->reportInvoice($company, $assigned, 'INV-CSV-OUTSTANDING', '44.44', '44.44', 'issued', today(), today()->addDay());
        $this->reportInvoice($company, $unassigned, 'INV-HIDDEN-OUTSTANDING', '55.55', '55.55', 'issued', today(), today()->addDay());

        $managerReport = $this->reportFor($manager, 'outstanding');
        $this->assertSame(4444, $managerReport['detail']['outstanding']);
        $this->actingAs($manager)->get('/reports/outstanding?outlet_id='.$unassigned->id)->assertSessionHasErrors('outlet_id');

        $detail = $this->actingAs($administrator)->get('/reports/outstanding?outlet_id='.$assigned->id);
        $csv = $this->actingAs($administrator)->get('/reports/outstanding/export?outlet_id='.$assigned->id);

        $detail->assertOk()->assertSee('INV-CSV-OUTSTANDING')->assertDontSee('INV-HIDDEN-OUTSTANDING');
        $csv->assertOk();
        $this->assertStringContainsString('INV-CSV-OUTSTANDING', $csv->streamedContent());
        $this->assertStringNotContainsString('INV-HIDDEN-OUTSTANDING', $csv->streamedContent());
    }
}

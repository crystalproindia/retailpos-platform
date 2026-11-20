<?php

namespace Tests\Feature;

use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmInvoice;
use App\Models\Crm\CrmInvoicePayment;
use App\Models\Finance\CrmInvoicePaymentAllocation;
use App\Models\User;
use App\Services\Crm\InvoicePdfService;
use App\Services\Crm\InvoicePaymentHistoryService;
use App\Services\Crm\InvoiceTemplateService;
use App\Services\Crm\PublicInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePaymentHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_with_no_payments_shows_a_compact_empty_state(): void
    {
        [$user, $invoice] = $this->invoice();

        $history = app(InvoicePaymentHistoryService::class)->forInvoice($invoice);
        $this->assertCount(0, $history['transactions']);
        $this->assertSame(0, $history['total_minor']);
        $this->assertTrue($history['reconciles_summary']);

        $render = app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']));
        $markup = view('invoice-templates.structured-gst-grid', ['invoice' => $invoice, 'render' => $render])->render();
        $this->assertStringContainsString('No payments received yet.', $markup);
    }

    public function test_single_advance_payment_renders_date_method_reference_and_notes(): void
    {
        [$user, $invoice] = $this->invoice();
        $this->payment($invoice, '20000.00', 'recorded', '2026-11-05', 'upi', 'UPI-ADV-100', 'Advance payment');
        $invoice->update(['amount_paid' => 20000, 'balance_due' => 80000, 'status' => InvoiceStatus::PartiallyPaid]);

        $render = app(InvoiceTemplateService::class)->renderData($invoice->fresh()->load(['company', 'items']));
        $markup = view('invoice-templates.premium-customer-download', ['invoice' => $invoice->fresh()->load(['company', 'items']), 'render' => $render])->render();

        $this->assertStringContainsString('05 Nov 2026', $markup);
        $this->assertStringContainsString('UPI', $markup);
        $this->assertStringContainsString('UPI-ADV-100', $markup);
        $this->assertStringContainsString('Advance payment', $markup);
        $this->assertStringContainsString('INR 20,000.00', $markup);
        $this->assertSame(2000000, $render['payment_history']['total_minor']);
        $this->assertTrue($render['payment_history']['reconciles_summary']);
    }

    public function test_multiple_partial_payments_are_oldest_first_and_reconcile_with_invoice_summary(): void
    {
        [$user, $invoice] = $this->invoice();
        $this->payment($invoice, '25000.00', 'cleared', '2026-11-18', 'bank_transfer', 'BANK-3');
        $this->payment($invoice, '20000.00', 'recorded', '2026-11-05', 'upi', 'UPI-1');
        $this->payment($invoice, '30000.00', 'recorded', '2026-11-11', 'cash');
        $invoice->update(['amount_paid' => 75000, 'balance_due' => 25000, 'status' => InvoiceStatus::PartiallyPaid]);

        $history = app(InvoicePaymentHistoryService::class)->forInvoice($invoice->fresh());
        $this->assertSame(['05 Nov 2026', '11 Nov 2026', '18 Nov 2026'], $history['transactions']->map(fn ($payment) => $payment['date']->format('d M Y'))->all());
        $this->assertSame(7500000, $history['total_minor']);
        $this->assertSame(2500000, (int) round(((float) $invoice->grand_total - (float) $invoice->amount_paid) * 100));
        $this->assertSame(2500000, (int) round((float) $invoice->balance_due * 100));
        $this->assertTrue($history['reconciles_summary']);
    }

    public function test_fully_paid_history_shows_every_posted_payment_and_zero_balance(): void
    {
        [$user, $invoice] = $this->invoice();
        $this->payment($invoice, '40000.00', 'recorded', '2026-11-05', 'upi');
        $this->payment($invoice, '60000.00', 'cleared', '2026-11-18', 'bank_transfer');
        $invoice->update(['amount_paid' => 100000, 'balance_due' => 0, 'status' => InvoiceStatus::Paid]);

        $history = app(InvoicePaymentHistoryService::class)->forInvoice($invoice->fresh());
        $this->assertCount(2, $history['transactions']);
        $this->assertSame(10000000, $history['total_minor']);
        $this->assertTrue($history['reconciles_summary']);
        $this->assertSame(0, (int) round((float) $invoice->balance_due * 100));
    }

    public function test_pending_failed_and_reversed_payments_are_excluded_but_legacy_direct_records_are_counted(): void
    {
        [$user, $invoice] = $this->invoice();
        $this->payment($invoice, '10000.00', 'pending', '2026-11-01', 'upi', 'PENDING');
        $this->payment($invoice, '10000.00', 'failed', '2026-11-02', 'cash', 'FAILED');
        $this->payment($invoice, '10000.00', 'reversed', '2026-11-03', 'card', 'REVERSED');
        $this->payment($invoice, '10000.00', 'recorded', '2026-11-04', 'cash', 'LEGACY', null, false);
        $invoice->update(['amount_paid' => 10000, 'balance_due' => 90000]);

        $history = app(InvoicePaymentHistoryService::class)->forInvoice($invoice->fresh());
        $this->assertCount(1, $history['transactions']);
        $this->assertSame('LEGACY', $history['transactions']->first()['reference']);
        $this->assertSame(1000000, $history['total_minor']);
        $this->assertTrue($history['reconciles_summary']);
    }

    public function test_allocated_payment_shows_only_the_amount_applied_to_this_invoice(): void
    {
        [$user, $invoice] = $this->invoice();
        $this->payment($invoice, '30000.00', 'recorded', '2026-11-05', 'bank_transfer', 'SPLIT-REF', null, true, '12000.00');
        $invoice->update(['amount_paid' => 12000, 'balance_due' => 88000]);

        $history = app(InvoicePaymentHistoryService::class)->forInvoice($invoice->fresh());
        $this->assertSame(1200000, $history['total_minor']);
        $this->assertSame('SPLIT-REF', $history['transactions']->first()['reference']);
        $this->assertTrue($history['reconciles_summary']);
    }

    public function test_legacy_aggregate_mismatch_is_reported_without_fabricating_a_payment(): void
    {
        [$user, $invoice] = $this->invoice(['amount_paid' => 2500, 'balance_due' => 97500]);

        $history = app(InvoicePaymentHistoryService::class)->forInvoice($invoice);
        $this->assertCount(0, $history['transactions']);
        $this->assertSame(0, $history['total_minor']);
        $this->assertSame(250000, $history['summary_minor']);
        $this->assertFalse($history['reconciles_summary']);

        $render = app(InvoiceTemplateService::class)->renderData($invoice->load(['company', 'items']));
        $markup = view('invoice-templates.structured-gst-grid', ['invoice' => $invoice, 'render' => $render])->render();
        $this->assertStringContainsString('invoice summary shows INR 2,500.00 received', $markup);
    }

    public function test_qr_still_uses_remaining_balance_after_recorded_payments(): void
    {
        [$user, $invoice] = $this->invoice();
        $this->payment($invoice, '60000.00', 'recorded', '2026-11-05', 'upi');
        $invoice->update(['amount_paid' => 60000, 'balance_due' => 40000, 'status' => InvoiceStatus::PartiallyPaid]);
        $templates = app(InvoiceTemplateService::class);
        $templates->setting($user->company)->update(['payment_qr_uri' => 'merchant@upi']);

        $render = $templates->renderData($invoice->fresh()->load(['company', 'items']));
        $this->assertStringContainsString('am=40000.00', $render['payment_qr_uri']);
    }

    public function test_invoice_detail_public_invoice_print_pdf_and_supported_paper_formats_render_history(): void
    {
        [$user, $invoice] = $this->invoice();
        $this->payment($invoice, '20000.00', 'recorded', '2026-11-05', 'upi', 'UPI-ADV-100');
        $this->payment($invoice, '30000.00', 'recorded', '2026-11-11', 'bank_transfer', 'BANK-2');
        $this->payment($invoice, '25000.00', 'recorded', '2026-11-18', 'upi', 'UPI-PART-3');
        $invoice->update(['amount_paid' => 75000, 'balance_due' => 25000, 'status' => InvoiceStatus::PartiallyPaid]);

        $this->actingAs($user)->get(route('sales.invoices.show', $invoice))->assertOk()->assertSee('PAYMENTS RECEIVED')->assertSee('UPI-ADV-100');
        $this->actingAs($user)->get(route('sales.invoices.print', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($user)->get(route('sales.invoices.pdf', $invoice))->assertOk()->assertHeader('content-type', 'application/pdf');

        $link = app(PublicInvoiceService::class)->issue($invoice, $user);
        $token = basename((string) parse_url($link->url, PHP_URL_PATH));
        $this->get(route('invoices.public.show', $token))->assertOk()->assertSee('PAYMENTS RECEIVED')->assertSee('UPI-ADV-100')->assertSee('Receipt PDF');
        $this->get(route('invoices.public.pdf', $token))->assertOk()->assertHeader('content-type', 'application/pdf');

        $templates = app(InvoiceTemplateService::class);
        $pdf = app(InvoicePdfService::class);
        foreach ([
            ['structured_gst_grid', 'a4'],
            ['a5_modern_retail', 'a5'],
            ['thermal_80_classic', 'thermal_80'],
            ['thermal_58_mini', 'thermal_58'],
        ] as [$templateKey, $paperFormat]) {
            $templates->update($user->company, $user, ['template_key' => $templateKey, 'paper_format' => $paperFormat, 'brand_color' => '#176ad8', 'copy_label' => 'original', 'orientation' => 'portrait', 'options' => $templates->defaultOptions()]);
            $render = $templates->renderData($invoice->fresh()->load(['company', 'items']));
            $markup = view($pdf->templateView($templateKey), ['invoice' => $invoice->fresh()->load(['company', 'items']), 'render' => $render])->render();
            $this->assertStringContainsString('Total Received', $markup, $templateKey);
            if ($paperFormat === 'thermal_58') {
                $this->assertStringNotContainsString('UPI-ADV-100', $markup);
                $this->assertStringNotContainsString('Bank Transfer', $markup);
                $this->assertStringContainsString('is-thermal-58', $markup);
            } else {
                $this->assertStringContainsString('UPI-ADV-100', $markup, $templateKey);
            }
            $this->assertStringStartsWith('%PDF-', $pdf->document($invoice->fresh()->load(['company', 'items']))->output());
        }
    }

    /** @return array{User,CrmInvoice} */
    private function invoice(array $overrides = []): array
    {
        $company = Company::factory()->create(['name' => 'Payment History Tenant', 'legal_name' => 'Payment History Tenant Ltd', 'currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create();
        $user = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
        $invoice = CrmInvoice::create(array_replace([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'invoice_number' => 'RPOS-PH-'.$company->id,
            'currency' => 'INR',
            'status' => InvoiceStatus::Issued,
            'issue_date' => '2026-11-01',
            'due_date' => '2026-11-30',
            'grand_total' => 100000,
            'amount_paid' => 0,
            'balance_due' => 100000,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ], $overrides));
        $invoice->items()->create(['name' => 'Payment history service', 'quantity' => 1, 'unit_price' => 100000, 'line_total' => 100000]);
        app(InvoiceTemplateService::class)->setting($company);

        return [$user, $invoice];
    }

    private function payment(CrmInvoice $invoice, string $amount, string $status, string $date, string $method, ?string $reference = null, ?string $notes = null, bool $allocate = true, ?string $allocationAmount = null): CrmInvoicePayment
    {
        static $sequence = 0;
        $sequence++;
        $payment = CrmInvoicePayment::create([
            'company_id' => $invoice->company_id,
            'branch_id' => $invoice->branch_id,
            'invoice_id' => $invoice->id,
            'payment_reference' => 'RPOS-PAY-'.$invoice->id.'-'.$sequence,
            'amount' => $amount,
            'allocated_amount' => $allocate ? ($allocationAmount ?? $amount) : '0.00',
            'unallocated_amount' => $allocate ? (string) ((float) $amount - (float) ($allocationAmount ?? $amount)) : $amount,
            'currency' => $invoice->currency,
            'payment_date' => $date,
            'payment_method' => $method,
            'transaction_reference' => $reference,
            'notes' => $notes,
            'status' => $status,
            'receipt_number' => 'RPOS-RCPT-'.$invoice->id.'-'.$sequence,
        ]);

        if ($allocate) {
            CrmInvoicePaymentAllocation::create([
                'company_id' => $invoice->company_id,
                'branch_id' => $invoice->branch_id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
                'amount' => $allocationAmount ?? $amount,
            ]);
        }

        return $payment;
    }
}

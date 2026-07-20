<?php

namespace Tests\Unit;

use App\Services\Compliance\GstTaxCalculator;
use App\Services\Compliance\GstinValidator;
use App\Services\Compliance\EinvoiceReadinessService;
use App\Models\Compliance\GstSetting;
use App\Models\Crm\CrmInvoice;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GstTaxCalculatorTest extends TestCase
{
    public function test_it_validates_gstin_structure_without_claiming_authenticity(): void
    {
        $validator = app(GstinValidator::class);
        $this->assertTrue($validator->isStructurallyValid('27ABCDE1234F1Z5'));
        $this->assertFalse($validator->isStructurallyValid('not-a-gstin'));
    }

    public function test_it_calculates_intra_state_cgst_and_sgst(): void
    {
        $tax = app(GstTaxCalculator::class)->calculate('100.00', '18', '27', '27');
        $this->assertSame('100.00', $tax['taxable_value']);
        $this->assertSame('9.00', $tax['cgst']);
        $this->assertSame('9.00', $tax['sgst']);
        $this->assertSame('0.00', $tax['igst']);
        $this->assertSame('118.00', $tax['line_total']);
    }

    public function test_it_calculates_inter_state_igst_and_requires_explicit_states(): void
    {
        $tax = app(GstTaxCalculator::class)->calculate('118.00', '18', '27', '29', true);
        $this->assertSame('100.00', $tax['taxable_value']);
        $this->assertSame('18.00', $tax['igst']);
        $this->expectException(ValidationException::class);
        app(GstTaxCalculator::class)->calculate('100', '18', null, '29');
    }

    public function test_einvoice_readiness_never_submits_or_invents_identifiers(): void
    {
        $invoice = new CrmInvoice(['invoice_number' => 'INV-1', 'currency' => 'INR', 'grand_total' => '100.00', 'e_invoice_status' => 'not_applicable']);
        $invoice->setRelation('items', collect());
        $settings = new GstSetting(['e_invoice_applicable' => false]);
        $result = app(EinvoiceReadinessService::class)->validate($invoice, $settings);
        $this->assertFalse($result['eligible']);
        $this->assertNull($result['payload']);
        $this->assertNull($invoice->irn ?? null);
    }
}

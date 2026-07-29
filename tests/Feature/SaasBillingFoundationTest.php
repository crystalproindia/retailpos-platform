<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Compliance\GstSetting;
use App\Models\SaasPlan;
use App\Models\User;
use App\Services\Saas\SaasSubscriptionInvoiceService;
use App\Services\Saas\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaasBillingFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_invoice_is_gst_calculated_and_idempotent_for_a_billing_period(): void
    {
        [$company, $administrator, $subscription] = $this->subscription();
        GstSetting::create([
            'company_id' => $company->id,
            'legal_name' => $company->legal_name,
            'gstin' => '27ABCDE1234F1Z5',
            'state_code' => '27',
            'default_place_of_supply_state_code' => '27',
        ]);

        $service = app(SaasSubscriptionInvoiceService::class);
        $invoice = $service->create($subscription, $administrator, ['idempotency_key' => 'invoice:'.$subscription->id.':period-1']);
        $again = $service->create($subscription, $administrator, ['idempotency_key' => 'invoice:'.$subscription->id.':period-1']);

        $this->assertSame($invoice->id, $again->id);
        $startYear = now()->month < 4 ? now()->year - 1 : now()->year;
        $this->assertSame('SBI-'.$startYear.'-'.str_pad((string) (($startYear + 1) % 100), 2, '0', STR_PAD_LEFT).'-000001', $invoice->invoice_number);
        $this->assertSame('1000.00', $invoice->taxable_total);
        $this->assertSame('90.00', $invoice->cgst_total);
        $this->assertSame('90.00', $invoice->sgst_total);
        $this->assertSame('1180.00', $invoice->grand_total);
        $this->assertSame('intra_state', $invoice->tax_treatment_snapshot);
        $this->assertCount(1, $invoice->items);

        $duplicateWithoutKey = $service->create($subscription, $administrator);
        $this->assertSame($invoice->id, $duplicateWithoutKey->id);
        $this->assertDatabaseCount('saas_subscription_invoices', 1);
    }

    public function test_manual_payments_support_partial_collection_and_renew_only_after_full_payment(): void
    {
        [, $administrator, $subscription] = $this->subscription();
        $service = app(SaasSubscriptionInvoiceService::class);
        $invoice = $service->create($subscription, $administrator);
        $service->issue($invoice, $administrator);
        $beforeRenewal = $subscription->renewal_date->toDateString();

        $service->recordManualPayment($invoice, $administrator, [
            'amount' => '400.00',
            'payment_method' => 'upi',
            'transaction_reference' => 'UPI-ONE',
            'idempotency_key' => 'payment-one',
        ]);
        $this->assertSame('partially_paid', $invoice->refresh()->status);
        $this->assertSame('600.00', $invoice->balance_due);
        $this->assertSame($beforeRenewal, $subscription->refresh()->renewal_date->toDateString());

        $payment = $service->recordManualPayment($invoice, $administrator, [
            'amount' => '600.00',
            'payment_method' => 'bank_transfer',
            'transaction_reference' => 'BANK-TWO',
            'idempotency_key' => 'payment-two',
        ]);

        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertSame('0.00', $invoice->balance_due);
        $this->assertNotNull($payment->receipt_number);
        $this->assertSame('active', $subscription->refresh()->status);
        $this->assertNotSame($beforeRenewal, $subscription->renewal_date->toDateString());
        $this->assertDatabaseCount('saas_billing_payments', 2);
    }

    public function test_overpayment_is_rejected_and_a_paid_invoice_cannot_be_voided(): void
    {
        [, $administrator, $subscription] = $this->subscription();
        $service = app(SaasSubscriptionInvoiceService::class);
        $invoice = $service->issue($service->create($subscription, $administrator), $administrator);

        try {
            $service->recordManualPayment($invoice, $administrator, ['amount' => '1000.01']);
            $this->fail('Expected an overpayment validation error.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $service->recordManualPayment($invoice, $administrator, ['amount' => '1000.00', 'idempotency_key' => 'full-payment']);
        $this->expectException(ValidationException::class);
        $service->void($invoice, $administrator, 'Superseded');
    }

    public function test_billing_permissions_remain_platform_only(): void
    {
        [$company, $administrator] = $this->companyAndAdministrator();
        $administrator->update(['is_platform_admin' => false]);
        $this->assertFalse($administrator->can('saas.billing.view'));

        $administrator->update(['is_platform_admin' => true]);
        $this->assertTrue($administrator->can('saas.billing.view'));

        $other = User::factory()->for($company)->create(['role' => UserRole::Staff, 'is_platform_admin' => true]);
        $this->assertFalse($other->can('saas.billing.issue'));
    }

    /** @return array{Company,User,\App\Models\SaasSubscription} */
    private function subscription(): array
    {
        [$company, $administrator] = $this->companyAndAdministrator();
        $plan = SaasPlan::create([
            'name' => 'Growth',
            'code' => 'growth-'.str()->random(8),
            'status' => 'active',
            'billing_interval' => 'monthly',
            'currency' => 'INR',
            'base_price' => 1000,
            'tax_percentage' => 18,
        ]);

        return [$company, $administrator, app(SubscriptionService::class)->create($company, $plan, $administrator)];
    }

    /** @return array{Company,User} */
    private function companyAndAdministrator(): array
    {
        $company = Company::factory()->create(['currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create();
        $administrator = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Administrator, 'is_platform_admin' => true]);

        return [$company, $administrator];
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\IntegrationConnection;
use App\Models\SaasPlan;
use App\Models\User;
use App\Services\Saas\SaasBillingCheckoutService;
use App\Services\Saas\SaasBillingOperationsService;
use App\Services\Saas\SaasSubscriptionInvoiceService;
use App\Services\Saas\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SaasOnlineBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_billing_and_tenant_billing_are_kept_separate(): void
    {
        [$company, $tenant, $platform, $subscription] = $this->subscription();
        $invoice = app(SaasSubscriptionInvoiceService::class)->issue(app(SaasSubscriptionInvoiceService::class)->create($subscription, $platform), $platform);

        $this->actingAs($tenant)->get(route('saas.billing.index'))->assertForbidden();
        $this->actingAs($platform)->get(route('saas.billing.index'))->assertOk()->assertSee($invoice->invoice_number);
        $this->actingAs($platform)->get(route('saas.billing.reports'))->assertOk()->assertSee('GST and credit summary');
        $this->actingAs($tenant)->get(route('account.subscription.billing.show', $invoice))->assertOk();

        $other = Company::factory()->create();
        $otherInvoice = $this->invoiceFor($other);
        $this->actingAs($tenant)->get(route('account.subscription.billing.show', $otherInvoice))->assertNotFound();
    }

    public function test_test_mode_checkout_uses_server_invoice_amount_and_confirms_payment_after_server_verification(): void
    {
        [$company, $tenant, $platform, $subscription] = $this->subscription();
        $invoice = app(SaasSubscriptionInvoiceService::class)->issue(app(SaasSubscriptionInvoiceService::class)->create($subscription, $platform), $platform);
        $this->gateway($platform);
        Http::fake(function ($request) {
            return str_contains($request->url(), '/orders')
                ? Http::response(['id' => 'order_test_123', 'status' => 'created'], 200)
                : Http::response(['id' => 'pay_test_123', 'order_id' => 'order_test_123', 'status' => 'captured', 'amount' => 100000, 'currency' => 'INR', 'method' => 'card'], 200);
        });
        $session = app(SaasBillingCheckoutService::class)->create($company, $invoice, $tenant);
        $this->assertSame('1000.00', $session->amount);
        $this->assertSame('order_test_123', $session->provider_order_id);

        $signature = hash_hmac('sha256', 'order_test_123|pay_test_123', 'rzp_test_secret');
        app(SaasBillingCheckoutService::class)->verifyCallback($session, $tenant, ['razorpay_payment_id' => 'pay_test_123', 'razorpay_order_id' => 'order_test_123', 'razorpay_signature' => $signature]);

        $this->assertSame('paid', $invoice->refresh()->status);
        $this->assertDatabaseHas('saas_billing_payments', ['provider' => 'razorpay', 'provider_payment_id' => 'pay_test_123', 'status' => 'confirmed']);
        $this->assertDatabaseHas('notification_deliveries', ['template_key' => 'saas_billing_invoice_issued']);
        $this->assertDatabaseHas('notification_deliveries', ['template_key' => 'saas_billing_receipt']);
    }

    public function test_signed_webhook_is_accepted_once_and_invalid_signature_is_rejected(): void
    {
        [, , $platform] = $this->subscription();
        $this->gateway($platform);
        $payload = json_encode(['event_id' => 'evt_test_1', 'event' => 'payment.failed', 'payload' => ['payment' => ['entity' => ['id' => 'pay_failed', 'order_id' => 'order_none', 'status' => 'failed', 'amount' => 100, 'currency' => 'INR']]]], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $payload, 'whsec_test');

        $this->call('POST', '/api/saas-billing/razorpay/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => $signature], $payload)->assertAccepted();
        $this->call('POST', '/api/saas-billing/razorpay/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => 'invalid'], $payload)->assertStatus(400);
        $this->assertDatabaseCount('saas_billing_webhook_events', 1);
    }

    public function test_invoice_generation_is_idempotent_and_uses_the_existing_billing_service(): void
    {
        [, , $platform, $subscription] = $this->subscription();
        $subscription->update(['renewal_date' => today(), 'current_period_ends_at' => today()]);
        $service = app(SaasBillingOperationsService::class);
        $service->generateInvoices(false, subscriptionId: $subscription->id);
        $service->generateInvoices(false, subscriptionId: $subscription->id);

        $this->assertDatabaseCount('saas_subscription_invoices', 1);
        $this->assertSame('issued', $subscription->invoices()->firstOrFail()->status);
        $this->actingAs($platform)->get(route('saas.billing.index'))->assertOk();
    }

    /** @return array{Company,User,User,\App\Models\SaasSubscription} */
    private function subscription(): array
    {
        $company = Company::factory()->create(['currency' => 'INR']);
        $branch = Branch::factory()->for($company)->create();
        $tenant = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Administrator]);
        $platform = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Administrator, 'is_platform_admin' => true]);
        $plan = SaasPlan::create(['name' => 'Growth', 'code' => 'growth-'.str()->random(8), 'status' => 'active', 'billing_interval' => 'monthly', 'currency' => 'INR', 'base_price' => 1000, 'tax_percentage' => 0]);
        return [$company, $tenant, $platform, app(SubscriptionService::class)->create($company, $plan, $platform)];
    }

    private function gateway(User $platform): void
    {
        IntegrationConnection::create(['company_id' => $platform->company_id, 'provider' => 'razorpay_saas_billing', 'name' => 'Razorpay SaaS Billing', 'access_token' => 'rzp_test_secret', 'refresh_token' => 'whsec_test', 'settings' => ['key_id' => 'rzp_test_key', 'mode' => 'test', 'enabled' => true], 'status' => 'connected', 'connected_by' => $platform->id, 'connected_at' => now()]);
    }

    private function invoiceFor(Company $company): \App\Models\SaasSubscriptionInvoice
    {
        $branch = Branch::factory()->for($company)->create();
        $administrator = User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Administrator, 'is_platform_admin' => true]);
        $plan = SaasPlan::create(['name' => 'Other', 'code' => 'other-'.str()->random(8), 'status' => 'active', 'billing_interval' => 'monthly', 'currency' => 'INR', 'base_price' => 100]);
        $subscription = app(SubscriptionService::class)->create($company, $plan, $administrator);
        return app(SaasSubscriptionInvoiceService::class)->create($subscription, $administrator);
    }
}

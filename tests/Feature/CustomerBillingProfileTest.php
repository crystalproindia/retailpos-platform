<?php

namespace Tests\Feature;

use App\Enums\Crm\CrmCustomerStatus;
use App\Enums\Crm\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmCustomerBillingProfile;
use App\Models\Crm\CrmInvoice;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\Crm\CustomerBillingProfileService;
use App\Services\Crm\InvoiceService;
use App\Services\Crm\InvoiceShareService;
use App\Services\Crm\PublicInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CustomerBillingProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_profile_becomes_an_immutable_invoice_snapshot(): void
    {
        $manager = $this->user();
        $customer = $this->customer($manager, 'ABC Enterprises');
        $profile = $this->profile($customer, $manager, ['profile_name' => 'ABC Technologies', 'legal_name' => 'ABC Technologies Private Limited', 'trade_name' => 'ABC Technologies', 'tax_number' => '33ABCDE1234F1Z5', 'email' => 'accounts@abc.test', 'phone' => '9000000000', 'billing_address' => '42 Tech Park', 'city' => 'Chennai', 'country' => 'India']);

        $invoice = app(InvoiceService::class)->create($manager, $this->invoicePayload(['customer_id' => $customer->id, 'billing_profile_id' => $profile->id, 'billing_name' => 'Untrusted override']));
        $this->assertSame($profile->id, $invoice->billing_profile_id);
        $this->assertSame('ABC Technologies', $invoice->billing_name);
        $this->assertSame('ABC Technologies Private Limited', $invoice->billing_company);
        $this->assertSame('33ABCDE1234F1Z5', $invoice->customer_tax_number);

        app(CustomerBillingProfileService::class)->update($customer, $profile, $manager, ['profile_name' => 'New identity', 'legal_name' => 'Changed legal name', 'tax_number' => '33ABCDE1234F1Z5', 'status' => 'active']);
        $this->assertSame('ABC Technologies Private Limited', $invoice->refresh()->billing_company);
        $this->assertSame('ABC Technologies', $invoice->billing_name);

        $invoice->update(['status' => InvoiceStatus::Issued, 'issue_date' => today()]);
        $this->actingAs($manager)->get(route('sales.invoices.show', $invoice))->assertOk()->assertSee('ABC Technologies Private Limited')->assertSee('33ABCDE1234F1Z5');
        $print = $this->get(route('sales.invoices.print', $invoice))->assertOk();
        $this->assertStringStartsWith('%PDF-', $print->getContent());
        $download = $this->get(route('sales.invoices.pdf', $invoice))->assertOk();
        $this->assertStringStartsWith('%PDF-', $download->getContent());

        Queue::fake();
        app(InvoiceShareService::class)->send($invoice->refresh(), $manager, 'billing@abc.test');
        $delivery = NotificationDelivery::query()->where('related_id', $invoice->id)->where('template_key', 'invoice_issued')->firstOrFail();
        $this->assertSame('Hello ABC Technologies,', $delivery->payload['greeting']);
    }

    public function test_primary_selection_disable_and_multiple_profiles_preserve_one_active_primary(): void
    {
        $manager = $this->user();
        $customer = $this->customer($manager, 'ABC Enterprises');
        $first = $this->profile($customer, $manager, ['profile_name' => 'ABC Enterprises', 'legal_name' => 'ABC Enterprises']);
        $second = $this->profile($customer, $manager, ['profile_name' => 'ABC Retail', 'legal_name' => 'ABC Retail']);
        $service = app(CustomerBillingProfileService::class);

        $this->assertTrue($first->refresh()->is_primary);
        $service->setPrimary($customer, $second, $manager);
        $this->assertFalse($first->refresh()->is_primary);
        $this->assertTrue($second->refresh()->is_primary);
        $service->disable($customer, $second, $manager);
        $this->assertSame('inactive', $second->refresh()->status);
        $this->assertTrue($first->refresh()->is_primary);
        $service->disable($customer, $first, $manager);
        $this->assertSame(0, $customer->billingProfiles()->where('status', 'active')->where('is_primary', true)->count());
        $this->assertSame(0, $customer->billingProfiles()->where('status', 'active')->count());
    }

    public function test_existing_customers_without_profiles_and_public_invoice_views_remain_compatible(): void
    {
        $manager = $this->user();
        $customer = $this->customer($manager, 'Legacy customer');
        $legacy = app(InvoiceService::class)->create($manager, $this->invoicePayload(['customer_id' => $customer->id, 'billing_name' => 'Legacy billing contact', 'billing_company' => 'Legacy customer']));
        $this->assertNull($legacy->billing_profile_id);
        $this->assertSame('Legacy customer', $legacy->billing_company);

        $profile = $this->profile($customer, $manager, ['profile_name' => 'Public identity', 'legal_name' => 'Original Public Legal Name']);
        $invoice = app(InvoiceService::class)->create($manager, $this->invoicePayload(['customer_id' => $customer->id, 'billing_profile_id' => $profile->id]));
        $link = app(PublicInvoiceService::class)->issue($invoice, $manager);
        $profile->update(['legal_name' => 'Changed after invoice']);
        $token = basename(parse_url($link->url, PHP_URL_PATH));
        $this->get('/i/'.$token)->assertOk()->assertSee('Original Public Legal Name')->assertDontSee('Changed after invoice');
    }

    public function test_cross_customer_and_inactive_profiles_are_rejected_without_creating_an_invoice(): void
    {
        $manager = $this->user();
        $first = $this->customer($manager, 'First customer');
        $second = $this->customer($manager, 'Second customer');
        $profile = $this->profile($second, $manager, ['profile_name' => 'Second legal identity', 'legal_name' => 'Second Legal']);
        $service = app(InvoiceService::class);

        try {
            $service->create($manager, $this->invoicePayload(['customer_id' => $first->id, 'billing_profile_id' => $profile->id]));
            $this->fail('Cross-customer profile was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('billing_profile_id', $exception->errors());
        }
        $profile->update(['status' => 'inactive']);
        try {
            $service->create($manager, $this->invoicePayload(['customer_id' => $second->id, 'billing_profile_id' => $profile->id]));
            $this->fail('Inactive profile was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('billing_profile_id', $exception->errors());
        }
        $this->assertDatabaseCount('crm_invoices', 0);
    }

    public function test_profile_routes_are_customer_and_tenant_scoped(): void
    {
        $manager = $this->user();
        $customer = $this->customer($manager, 'ABC Enterprises');
        $this->actingAs($manager)->post("/crm/customers/{$customer->id}/billing-profiles", ['profile_name' => 'ABC Retail', 'legal_name' => 'ABC Retail', 'tax_number' => '33ABCDE1234F1Z5', 'status' => 'active'])->assertRedirect();
        $profile = CrmCustomerBillingProfile::query()->firstOrFail();
        $this->actingAs($manager)->post("/crm/customers/{$customer->id}/billing-profiles/{$profile->id}/primary")->assertRedirect();
        $other = $this->user();
        $this->actingAs($other)->get("/crm/customers/{$customer->id}/billing-profiles/{$profile->id}/edit")->assertNotFound();
    }

    private function profile(CrmCustomer $customer, User $user, array $attributes): CrmCustomerBillingProfile
    {
        return app(CustomerBillingProfileService::class)->create($customer, $user, array_merge(['status' => 'active', 'tax_number' => null], $attributes));
    }

    private function invoicePayload(array $extra = []): array
    {
        return array_merge(['currency' => 'INR', 'items' => [['name' => 'Implementation', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 0]]], $extra);
    }

    private function customer(User $user, string $name): CrmCustomer
    {
        return CrmCustomer::create(['company_id' => $user->company_id, 'customer_code' => 'RPC-'.str_pad((string) (CrmCustomer::query()->count() + 1), 6, '0', STR_PAD_LEFT), 'company_name' => $name, 'display_name' => $name, 'status' => CrmCustomerStatus::Active, 'created_by' => $user->id]);
    }

    private function user(): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }
}

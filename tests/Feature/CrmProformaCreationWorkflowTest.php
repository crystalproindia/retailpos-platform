<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Compliance\GstSetting;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\InvoiceTemplateSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmProformaCreationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_users_see_the_create_action_and_can_open_a_preselected_customer_form(): void
    {
        $user = $this->manager();
        $customer = $this->customer($user, 'Northwind Retail');

        $this->actingAs($user)->get(route('crm.proformas.index'))
            ->assertOk()
            ->assertSee('+ Create Proforma Invoice')
            ->assertSee(route('crm.proformas.create'), false);

        $this->actingAs($user)->get(route('crm.proformas.create', ['customer_id' => $customer->id]))
            ->assertOk()
            ->assertSee('Create proforma invoice')
            ->assertSee('Northwind Retail')
            ->assertSee('billing@northwind.test')
            ->assertSee('data-proforma-customer-select', false);

        $this->actingAs($user)->get(route('crm.customers.show', $customer))
            ->assertOk()
            ->assertSee('Create Proforma')
            ->assertSee(route('crm.proformas.create', ['customer_id' => $customer->id]), false);
    }

    public function test_cross_tenant_customer_preselection_and_submission_are_rejected(): void
    {
        $user = $this->manager();
        $otherCompany = Company::factory()->create();
        $otherCustomer = CrmCustomer::create([
            'company_id' => $otherCompany->id,
            'customer_code' => 'OTHER-001',
            'company_name' => 'Other Tenant',
            'display_name' => 'Other Contact',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get(route('crm.proformas.create', ['customer_id' => $otherCustomer->id]))->assertNotFound();
        $this->actingAs($user)->post(route('crm.proformas.store'), $this->payload(['customer_id' => $otherCustomer->id]))->assertNotFound();
        $this->assertDatabaseCount('crm_proforma_invoices', 0);
    }

    public function test_authorized_user_creates_a_proforma_with_authoritative_customer_and_presentation_snapshots(): void
    {
        $user = $this->manager();
        $customer = $this->customer($user, 'Apex Stores');
        InvoiceTemplateSetting::create([
            'company_id' => $user->company_id,
            'template_key' => 'structured_gst_grid',
            'watermark_enabled' => true,
            'watermark_path' => 'company-watermarks/apex.png',
            'options' => ['show_payment_details_on_proforma' => true],
            'account_holder_name' => 'Apex Retail Pvt Ltd',
            'bank_name' => 'Example Bank',
            'account_number' => '1234567890',
        ]);

        $this->actingAs($user)->post(route('crm.proformas.store'), $this->payload([
            'customer_id' => $customer->id,
            'customer_name' => 'Forged Contact',
            'customer_company' => 'Forged Company',
            'customer_email' => 'forged@example.test',
            'items' => [[
                'name' => 'Implementation service',
                'description' => 'Initial setup',
                'quantity' => 2,
                'unit_price' => 100,
                'discount_amount' => 10,
                'tax_rate' => 18,
            ]],
        ]))->assertRedirect();

        $proforma = CrmProformaInvoice::query()->firstOrFail();
        $this->assertSame($customer->id, $proforma->customer_id);
        $this->assertSame('Apex Stores', $proforma->customer_company);
        $this->assertSame('Apex Contact', $proforma->customer_name);
        $this->assertSame('200.00', $proforma->subtotal);
        $this->assertSame('10.00', $proforma->discount_total);
        $this->assertSame('34.20', $proforma->tax_total);
        $this->assertSame('224.20', $proforma->grand_total);
        $this->assertStringStartsWith('RPI-', $proforma->proforma_number);
        $this->assertNotNull($proforma->presentation_snapshot_at);
        $this->assertSame('company-watermarks/apex.png', $proforma->watermark_path_snapshot);
        $this->assertSame('Apex Retail Pvt Ltd', $proforma->payment_details_snapshot['account_holder_name']);
    }

    public function test_no_gst_proforma_uses_the_existing_server_authoritative_tax_mode_rules(): void
    {
        $user = $this->manager();
        GstSetting::create(['company_id' => $user->company_id, 'legal_name' => $user->company->name, 'registration_type' => 'exempt']);

        $this->actingAs($user)->post(route('crm.proformas.store'), $this->payload([
            'tax_mode' => 'no_gst',
            'items' => [[
                'name' => 'Exempt service',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_amount' => 0,
                'tax_rate' => 18,
            ]],
        ]))->assertRedirect();

        $proforma = CrmProformaInvoice::query()->firstOrFail();
        $this->assertSame('no_gst', $proforma->tax_mode);
        $this->assertSame('0.00', $proforma->tax_total);
        $this->assertSame('100.00', $proforma->grand_total);
        $this->assertSame('0.000', $proforma->items->firstOrFail()->tax_rate);
    }

    public function test_items_are_required_and_unauthorized_users_cannot_open_or_create_proformas(): void
    {
        $manager = $this->manager();
        $staff = User::factory()->for($manager->company)->create(['branch_id' => $manager->branch_id, 'role' => UserRole::Staff]);

        $this->actingAs($manager)->post(route('crm.proformas.store'), $this->payload(['items' => []]))
            ->assertSessionHasErrors('items');

        $this->actingAs($staff)->get(route('crm.proformas.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('crm.proformas.store'), $this->payload())->assertForbidden();
    }

    private function manager(): User
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => UserRole::Manager]);
    }

    private function customer(User $user, string $companyName): CrmCustomer
    {
        return CrmCustomer::create([
            'company_id' => $user->company_id,
            'customer_code' => 'CUST-'.CrmCustomer::query()->count(),
            'company_name' => $companyName,
            'display_name' => str_replace('Stores', 'Contact', $companyName),
            'email' => $companyName === 'Northwind Retail' ? 'billing@northwind.test' : 'billing@apex.test',
            'phone' => '9000000000',
            'billing_address' => '12 Market Road',
            'status' => 'active',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Implementation proposal',
            'customer_name' => 'Walk-in customer',
            'customer_company' => 'Walk-in Retail',
            'customer_email' => 'walkin@example.test',
            'customer_phone' => '9000000000',
            'billing_address' => '1 Main Street',
            'currency' => 'INR',
            'tax_mode' => 'gst',
            'invoice_date' => today()->toDateString(),
            'due_date' => today()->addDays(7)->toDateString(),
            'items' => [[
                'name' => 'Setup',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_amount' => 0,
                'tax_rate' => 18,
            ]],
        ], $overrides);
    }
}

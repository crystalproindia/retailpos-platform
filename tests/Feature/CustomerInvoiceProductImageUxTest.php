<?php

namespace Tests\Feature;

use App\Enums\Crm\CrmCustomerStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmInvoice;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Services\Inventory\ProductImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerInvoiceProductImageUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_opens_a_prefilled_invoice_and_cross_tenant_customer_is_rejected(): void
    {
        $manager = $this->user();
        $customer = $this->customer($manager);

        $this->actingAs($manager)
            ->get(route('sales.invoices.create', ['customer' => $customer->id]))
            ->assertOk()
            ->assertSee('Northstar Retail')
            ->assertSee('27ABCDE1234F1Z5')
            ->assertSee('Create and select customer');

        $outside = $this->user();
        $this->actingAs($outside)
            ->get(route('sales.invoices.create', ['customer' => $customer->id]))
            ->assertNotFound();
    }

    public function test_invoice_customer_search_supports_name_phone_email_and_gstin(): void
    {
        $manager = $this->user();
        $customer = $this->customer($manager);

        foreach (['Northstar', '9000011111', 'asha@example.test', '27ABCDE1234F1Z5'] as $term) {
            $this->actingAs($manager)
                ->getJson(route('sales.invoices.customers.search', ['q' => $term]))
                ->assertOk()
                ->assertJsonPath('customers.0.id', $customer->id)
                ->assertJsonPath('customers.0.company_name', 'Northstar Retail');
        }
    }

    public function test_invoice_customer_search_aggregates_outstanding_without_per_customer_queries(): void
    {
        $manager = $this->user();
        foreach (range(1, 3) as $sequence) {
            $customer = CrmCustomer::create([
                'company_id' => $manager->company_id,
                'customer_code' => 'RPC-2026-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                'company_name' => 'Aggregate Retail '.$sequence,
                'display_name' => 'Aggregate Customer '.$sequence,
                'status' => CrmCustomerStatus::Active,
                'created_by' => $manager->id,
            ]);
            $this->actingAs($manager)->post(route('sales.invoices.store'), $this->invoicePayload(['customer_id' => $customer->id]));
        }

        $invoiceQueries = 0;
        DB::listen(function ($query) use (&$invoiceQueries): void {
            if (str_contains($query->sql, 'crm_invoices')) {
                $invoiceQueries++;
            }
        });

        $this->actingAs($manager)
            ->getJson(route('sales.invoices.customers.search', ['q' => 'Aggregate Retail']))
            ->assertOk()
            ->assertJsonCount(3, 'customers')
            ->assertJsonPath('customers.0.outstanding', '1180');
        $this->assertSame(1, $invoiceQueries);
    }

    public function test_customer_can_be_quick_created_selected_and_duplicate_is_rejected(): void
    {
        $manager = $this->user();
        $payload = [
            'name' => 'Mira Shah',
            'company_name' => 'Mira Stores',
            'email' => 'mira@example.test',
            'phone' => '9000022222',
            'tax_number' => '27AAAAA0000A1Z5',
            'billing_address' => '12 Market Road, Pune',
        ];

        $response = $this->actingAs($manager)
            ->postJson(route('sales.invoices.customers.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('customer.company_name', 'Mira Stores');

        $customerId = $response->json('customer.id');
        $this->assertDatabaseHas('crm_customers', ['id' => $customerId, 'company_id' => $manager->company_id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.customer.quick_created_from_invoice', 'auditable_id' => $customerId]);

        $this->actingAs($manager)
            ->postJson(route('sales.invoices.customers.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonPath('errors.customer.0', 'A customer with this email or phone already exists. Select the existing customer instead.');
        $this->assertDatabaseCount('crm_customers', 1);
    }

    public function test_quick_customer_validation_and_authorization_are_enforced(): void
    {
        $manager = $this->user();
        $this->actingAs($manager)
            ->postJson(route('sales.invoices.customers.store'), ['name' => '', 'email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);

        $staff = $this->user(UserRole::Staff, $manager->company, $manager->branch);
        $this->actingAs($staff)
            ->postJson(route('sales.invoices.customers.store'), ['name' => 'Unauthorized customer'])
            ->assertForbidden();

        $this->assertDatabaseCount('crm_customers', 0);
    }

    public function test_selected_customer_is_stored_and_invoice_appears_in_customer_history(): void
    {
        $manager = $this->user();
        $customer = $this->customer($manager);

        $this->actingAs($manager)
            ->post(route('sales.invoices.store'), $this->invoicePayload(['customer_id' => $customer->id]))
            ->assertRedirect();

        $invoice = CrmInvoice::query()->firstOrFail();
        $this->assertSame($customer->id, $invoice->customer_id);
        $this->assertSame('Northstar Retail', $invoice->billing_company);
        $this->assertSame('27ABCDE1234F1Z5', $invoice->customer_tax_number);
        $this->actingAs($manager)
            ->get(route('crm.customers.show', $customer))
            ->assertOk()
            ->assertSee($invoice->invoice_number)
            ->assertSee('Commercial history');
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.invoice.created_from_customer', 'auditable_id' => $invoice->id]);
    }

    public function test_walk_in_invoice_remains_supported(): void
    {
        $manager = $this->user();

        $this->actingAs($manager)
            ->post(route('sales.invoices.store'), $this->invoicePayload(['billing_name' => 'Walk-in customer']))
            ->assertRedirect();

        $this->assertDatabaseHas('crm_invoices', ['company_id' => $manager->company_id, 'customer_id' => null, 'billing_name' => 'Walk-in customer']);
    }

    public function test_changing_a_draft_invoice_customer_is_authorized_and_audited(): void
    {
        $manager = $this->user();
        $first = $this->customer($manager);
        $second = CrmCustomer::create([
            'company_id' => $manager->company_id,
            'customer_code' => 'RPC-2026-000002',
            'company_name' => 'Second Retail',
            'display_name' => 'Second Customer',
            'status' => CrmCustomerStatus::Active,
            'created_by' => $manager->id,
        ]);

        $this->actingAs($manager)->post(route('sales.invoices.store'), $this->invoicePayload(['customer_id' => $first->id]));
        $invoice = CrmInvoice::query()->firstOrFail();
        $this->actingAs($manager)
            ->put(route('sales.invoices.update', $invoice), $this->invoicePayload(['customer_id' => $second->id]))
            ->assertRedirect();

        $this->assertSame($second->id, $invoice->refresh()->customer_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'crm.invoice.customer_changed', 'auditable_id' => $invoice->id]);
    }

    public function test_sales_user_can_invoice_a_customer_created_from_the_invoice_drawer(): void
    {
        $sales = $this->user(UserRole::Sales);

        $customerResponse = $this->actingAs($sales)->postJson(route('sales.invoices.customers.store'), [
            'name' => 'Sales-created customer',
            'phone' => '9000099999',
        ])->assertCreated();

        $customerId = $customerResponse->json('customer.id');
        $this->actingAs($sales)
            ->post(route('sales.invoices.store'), $this->invoicePayload(['customer_id' => $customerId]))
            ->assertRedirect();

        $invoice = CrmInvoice::query()->where('customer_id', $customerId)->firstOrFail();
        $this->actingAs($sales)->get(route('sales.invoices.show', $invoice))->assertOk();
        $this->actingAs($sales)->get(route('crm.customers.show', $customerId))->assertOk();
    }

    public function test_png_jpg_and_webp_product_images_are_stored_in_private_tenant_paths(): void
    {
        Storage::fake('local');
        $manager = $this->user();
        $unit = $this->unit($manager);

        foreach (['png', 'jpg', 'webp'] as $index => $extension) {
            $file = UploadedFile::fake()->image('product.'.$extension, 2400, 1800);
            $this->actingAs($manager)
                ->post(route('inventory.products.store'), $this->productPayload($unit, $index + 1, ['product_image' => $file]))
                ->assertRedirect();
        }

        $products = Product::query()->where('company_id', $manager->company_id)->orderBy('id')->get();
        $this->assertCount(3, $products);
        foreach ($products as $product) {
            $this->assertStringStartsWith("companies/{$manager->company_id}/products/{$product->id}/", $product->image);
            Storage::disk('local')->assertExists($product->image);
            [$primaryWidth, $primaryHeight] = getimagesize(Storage::disk('local')->path($product->image));
            $this->assertLessThanOrEqual(1600, max($primaryWidth, $primaryHeight));
            $thumbnailPath = dirname($product->image).'/thumbnail-'.basename($product->image);
            Storage::disk('local')->assertExists($thumbnailPath);
            [$width, $height] = getimagesize(Storage::disk('local')->path($thumbnailPath));
            $this->assertLessThanOrEqual(320, max($width, $height));
            $this->actingAs($manager)->get(route('inventory.products.image', $product))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            $thumbnailResponse = $this->actingAs($manager)->get(route('inventory.products.image', [$product, 'variant' => 'thumbnail']))->assertOk();
            $this->assertStringContainsString('private', (string) $thumbnailResponse->headers->get('Cache-Control'));
            $this->assertStringContainsString('max-age=86400', (string) $thumbnailResponse->headers->get('Cache-Control'));
        }
    }

    public function test_product_image_validation_replacement_removal_and_tenant_access(): void
    {
        Storage::fake('local');
        $manager = $this->user();
        $unit = $this->unit($manager);

        $this->actingAs($manager)
            ->post(route('inventory.products.store'), $this->productPayload($unit, 1, ['product_image' => UploadedFile::fake()->image('first.png', 100, 100)]))
            ->assertRedirect();
        $product = Product::query()->firstOrFail();
        $firstPath = $product->image;
        $firstThumbnailPath = dirname($firstPath).'/thumbnail-'.basename($firstPath);

        DB::beginTransaction();
        app(ProductImageService::class)->replace($product, $manager, UploadedFile::fake()->image('rolled-back.jpg', 100, 100));
        DB::rollBack();
        $this->assertSame($firstPath, $product->refresh()->image);
        Storage::disk('local')->assertExists($firstPath);
        Storage::disk('local')->assertExists($firstThumbnailPath);

        $this->actingAs($manager)
            ->put(route('inventory.products.update', $product), $this->productPayload($unit, 1, ['product_image' => UploadedFile::fake()->image('second.jpg', 100, 100)]))
            ->assertRedirect();
        $secondPath = $product->refresh()->image;
        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertMissing($firstThumbnailPath);
        Storage::disk('local')->assertExists($secondPath);
        $secondThumbnailPath = dirname($secondPath).'/thumbnail-'.basename($secondPath);
        Storage::disk('local')->assertExists($secondThumbnailPath);
        $this->assertDatabaseHas('audit_logs', ['event' => 'inventory.product.image_replaced', 'auditable_id' => $product->id]);

        $outside = $this->user();
        $this->actingAs($outside)->get(route('inventory.products.image', $product))->assertNotFound();
        $this->actingAs($outside)->delete(route('inventory.products.image.destroy', $product))->assertNotFound();
        $sales = $this->user(UserRole::Sales, $manager->company, $manager->branch);
        $this->actingAs($sales)
            ->delete(route('inventory.products.image.destroy', $product))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('inventory.products.edit', $product))
            ->assertOk()
            ->assertSee('Remove Image')
            ->assertSee(route('inventory.products.image.destroy', $product));
        $this->actingAs($manager)
            ->delete(route('inventory.products.image.destroy', $product))
            ->assertRedirect();
        $this->assertNull($product->refresh()->image);
        Storage::disk('local')->assertMissing($secondPath);
        Storage::disk('local')->assertMissing($secondThumbnailPath);
        $this->assertDatabaseHas('audit_logs', ['event' => 'inventory.product.image_removed', 'auditable_id' => $product->id]);

        $this->actingAs($manager)
            ->post(route('inventory.products.store'), $this->productPayload($unit, 2, ['product_image' => UploadedFile::fake()->create('payload.svg', 2, 'image/svg+xml')]))
            ->assertSessionHasErrors('product_image');
        $this->actingAs($manager)
            ->post(route('inventory.products.store'), $this->productPayload($unit, 3, ['product_image' => UploadedFile::fake()->create('large.png', 3000, 'image/png')]))
            ->assertSessionHasErrors('product_image');
    }

    public function test_product_image_and_fallback_are_present_on_operational_surfaces(): void
    {
        Storage::fake('local');
        $manager = $this->user();
        $unit = $this->unit($manager);
        $this->actingAs($manager)
            ->post(route('inventory.products.store'), $this->productPayload($unit, 1, ['product_image' => UploadedFile::fake()->image('product.png', 100, 100), 'allow_negative_stock' => 1]))
            ->assertRedirect();
        $product = Product::query()->firstOrFail();
        $thumbnailUrl = route('inventory.products.image', [$product, 'variant' => 'thumbnail']);

        $this->actingAs($manager)->get(route('inventory.products.index'))->assertOk()->assertSee($thumbnailUrl);
        $this->actingAs($manager)->get(route('inventory.products.show', $product))->assertOk()->assertSee(route('inventory.products.image', $product));
        $this->actingAs($manager)->get(route('inventory.stock.availability', ['search' => 'Image Product']))->assertOk()->assertSee($thumbnailUrl);
        $this->actingAs($manager)->get(route('inventory.stock.product', $product))->assertOk()->assertSee($thumbnailUrl);
        $this->actingAs($manager)->get(route('pos.index'))->assertOk()->assertSee($thumbnailUrl);
        $this->actingAs($manager)
            ->getJson(route('pos.offline.bootstrap'))
            ->assertOk()
            ->assertJsonPath('products.0.image', $thumbnailUrl);
        $this->assertStringNotContainsString('companies/', (string) $this->actingAs($manager)->getJson(route('pos.offline.bootstrap'))->json('products.0.image'));

        $source = $this->warehouse($manager, 'IMAGE-SOURCE');
        $destination = $this->warehouse($manager, 'IMAGE-DEST');
        $this->actingAs($manager)->getJson(route('inventory.transfers.products', [
            'q' => 'Image Product',
            'source_warehouse_id' => $source->id,
            'destination_warehouse_id' => $destination->id,
        ]))->assertOk()->assertJsonPath('products.0.image', $thumbnailUrl);

        $thumbnailPath = dirname($product->image).'/thumbnail-'.basename($product->image);
        Storage::disk('local')->delete($thumbnailPath);
        $this->actingAs($manager)->get(route('inventory.products.index'))->assertOk()->assertDontSee($thumbnailUrl);

        $product->update(['image' => 'companies/'.$manager->company_id.'/products/'.$product->id.'/missing.png']);
        $this->actingAs($manager)->get(route('inventory.products.show', $product))->assertOk()->assertDontSee('<img', false);
        $this->actingAs($manager)->get(route('inventory.products.image', $product))->assertNotFound();
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function invoicePayload(array $overrides = []): array
    {
        return array_replace([
            'billing_name' => null,
            'billing_company' => null,
            'billing_email' => null,
            'billing_phone' => null,
            'billing_address' => null,
            'billing_country' => null,
            'customer_tax_number' => null,
            'currency' => 'INR',
            'items' => [['name' => 'RetailPOS subscription', 'quantity' => 1, 'unit_price' => 1000, 'tax_rate' => 18]],
        ], $overrides);
    }

    private function customer(User $user): CrmCustomer
    {
        return CrmCustomer::create([
            'company_id' => $user->company_id,
            'customer_code' => 'RPC-2026-000001',
            'company_name' => 'Northstar Retail',
            'display_name' => 'Asha Mehta',
            'email' => 'asha@example.test',
            'phone' => '9000011111',
            'country' => 'India',
            'billing_address' => '10 Market Road, Mumbai',
            'tax_number' => '27ABCDE1234F1Z5',
            'status' => CrmCustomerStatus::Active,
            'created_by' => $user->id,
        ]);
    }

    private function unit(User $user): InventoryUnit
    {
        return InventoryUnit::create(['company_id' => $user->company_id, 'name' => 'Piece', 'short_code' => 'PCS', 'type' => 'quantity', 'is_active' => true]);
    }

    private function warehouse(User $user, string $code): Warehouse
    {
        return Warehouse::create([
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'name' => str($code)->headline(),
            'code' => $code,
            'type' => 'store',
            'country' => 'India',
            'is_primary' => false,
            'is_active' => true,
        ]);
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function productPayload(InventoryUnit $unit, int $sequence, array $overrides = []): array
    {
        return array_replace([
            'name' => 'Image Product '.$sequence,
            'sku' => 'IMAGE-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
            'unit_id' => $unit->id,
            'type' => 'simple',
            'selling_price' => 499,
            'status' => 'active',
            'track_inventory' => 1,
            'attribute_value_ids' => [''],
        ], $overrides);
    }

    private function user(UserRole $role = UserRole::Manager, ?Company $company = null, ?Branch $branch = null): User
    {
        $company ??= Company::factory()->create();
        $branch ??= Branch::factory()->for($company)->create();

        return User::factory()->for($company)->create(['branch_id' => $branch->id, 'role' => $role]);
    }
}

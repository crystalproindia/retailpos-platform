<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Crm\CrmCustomer;
use App\Models\Customers\Customer;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryTaxRate;
use App\Models\Inventory\InventoryUnit;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\InvoiceTemplateSetting;
use App\Models\Purchases\Supplier;
use App\Models\User;
use App\Services\Crm\InvoiceService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

const EXPECTED_PRE_PHASE_G = 'e5f18104790d9eac8a669c79a428940dc73b33ce';
const EXPECTED_PHASE_G = 'e9f46ba1732fd87861a71906d8717a5ffc732e1e';

try {
    $arguments = parseArguments(array_slice($argv, 1));
    $command = array_shift($arguments['_']);
    $appRoot = requireOption($arguments, 'app-root');
    $repositoryRoot = requireOption($arguments, 'repository-root');

    $loader = require $repositoryRoot.'/vendor/autoload.php';
    // Composer's optimized class map belongs to the caller checkout. Pin the one
    // service used to create the pre-Phase-G invoice fixture to the detached app.
    $loader->addClassMap([
        'App\\Services\\Crm\\InvoiceService' => $appRoot.'/app/Services/Crm/InvoiceService.php',
    ]);
    $loader->setPsr4('App\\', [$appRoot.'/app']);
    $loader->setPsr4('Database\\Factories\\', [$appRoot.'/database/factories']);
    $loader->setPsr4('Database\\Seeders\\', [$appRoot.'/database/seeders']);

    chdir($appRoot);
    $app = require $appRoot.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    DB::statement('PRAGMA foreign_keys = ON');

    match ($command) {
        'migrate' => migrate($arguments),
        'seed' => seed(),
        'snapshot' => writeSnapshot(
            requireOption($arguments, 'output'),
            snapshot(requireOption($arguments, 'stage'))
        ),
        'verify' => verify(
            requireOption($arguments, 'before'),
            requireOption($arguments, 'after'),
            requireOption($arguments, 'retry-output')
        ),
        default => throw new RuntimeException('Unknown command: '.($command ?? '(missing)')),
    };
} catch (Throwable $exception) {
    fwrite(STDERR, "Phase G history verification failed: {$exception->getMessage()}\n");
    exit(1);
}

/** @return array<string, mixed> */
function parseArguments(array $arguments): array
{
    $parsed = ['_' => []];

    foreach ($arguments as $argument) {
        if (! str_starts_with($argument, '--')) {
            $parsed['_'][] = $argument;
            continue;
        }

        [$key, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, true);
        $parsed[$key] = $value;
    }

    return $parsed;
}

/** @param array<string, mixed> $arguments */
function requireOption(array $arguments, string $name): string
{
    $value = $arguments[$name] ?? null;

    if (! is_string($value) || $value === '') {
        throw new InvalidArgumentException("Missing --{$name}=...");
    }

    return $value;
}

/** @param array<string, mixed> $arguments */
function migrate(array $arguments): void
{
    $options = ['--force' => true, '--no-interaction' => true];

    if (isset($arguments['migration-path'])) {
        $options['--path'] = requireOption($arguments, 'migration-path');
        $options['--realpath'] = true;
    }

    $status = Artisan::call('migrate', $options);
    fputs(STDOUT, Artisan::output());

    if ($status !== 0) {
        throw new RuntimeException("Migration command exited with status {$status}.");
    }
}

function seed(): void
{
    CarbonImmutable::setTestNow('2026-07-27 10:00:00');

    DB::transaction(function (): void {
        $tenantOne = Company::factory()->create([
            'name' => 'History Tenant One',
            'legal_name' => 'History Tenant One Retail Private Limited',
            'tax_id' => 'TENANT1GSTIN',
            'email' => 'accounts@tenant-one.example.test',
            'phone' => '+91-9000000001',
            'address' => '11 Historical Market Road',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'country' => 'India',
            'postal_code' => '411001',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'is_active' => true,
        ]);
        $tenantTwo = Company::factory()->create([
            'name' => 'History Tenant Two',
            'legal_name' => 'History Tenant Two Stores Private Limited',
            'tax_id' => 'TENANT2GSTIN',
            'email' => 'accounts@tenant-two.example.test',
            'phone' => '+91-9000000002',
            'address' => '22 Archive Bazaar Lane',
            'city' => 'Jaipur',
            'state' => 'Rajasthan',
            'country' => 'India',
            'postal_code' => '302001',
            'timezone' => 'Asia/Kolkata',
            'currency' => 'INR',
            'is_active' => true,
        ]);

        $tenantOnePrimary = Branch::factory()->create([
            'company_id' => $tenantOne->id,
            'name' => 'Tenant One Central Branch',
            'code' => 'T1-CENTRAL',
            'email' => 'central@tenant-one.example.test',
            'phone' => '+91-9111111111',
            'address' => '11 Historical Market Road',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'country' => 'India',
            'is_primary' => true,
            'is_active' => true,
        ]);
        $tenantOneSecondary = Branch::factory()->create([
            'company_id' => $tenantOne->id,
            'name' => 'Tenant One Riverside Branch',
            'code' => 'T1-RIVER',
            'email' => 'river@tenant-one.example.test',
            'phone' => '+91-9111111112',
            'address' => '12 Historical Market Road',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'country' => 'India',
            'is_primary' => false,
            'is_active' => true,
        ]);

        $users = [
            createUser($tenantOne, $tenantOnePrimary, 'Tenant One Administrator', 'admin@tenant-one.example.test', UserRole::Administrator),
            createUser($tenantOne, $tenantOneSecondary, 'Tenant One Manager', 'manager@tenant-one.example.test', UserRole::Manager),
            createUser($tenantTwo, null, 'Tenant Two Administrator', 'admin@tenant-two.example.test', UserRole::Administrator),
            createUser($tenantTwo, null, 'Tenant Two Staff', 'staff@tenant-two.example.test', UserRole::Staff),
        ];

        createTenantHistory($tenantOne, $tenantOnePrimary, $users[0], 'T1', [
            ['branch' => $tenantOnePrimary, 'code' => 'T1-WH-CENTRAL', 'name' => 'Tenant One Central Warehouse'],
            ['branch' => $tenantOneSecondary, 'code' => 'T1-WH-RIVER', 'name' => 'Tenant One Riverside Warehouse'],
        ]);
        createTenantHistory($tenantTwo, null, $users[2], 'T2', [
            ['branch' => null, 'code' => 'T2-WH-HIST', 'name' => 'Tenant Two Historical Warehouse'],
        ]);
    });

    CarbonImmutable::setTestNow();
}

function createUser(Company $company, ?Branch $branch, string $name, string $email, UserRole $role): User
{
    return User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch?->id,
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'is_active' => true,
        'verification_status' => 'verified',
        'email_verified_at' => '2026-07-01 09:00:00',
        'password' => 'historical-fixture-password',
        'remember_token' => 'historical-token-'.$company->id.'-'.$role->value,
    ]);
}

/** @param array<int, array{branch: ?Branch, code: string, name: string}> $warehouseDefinitions */
function createTenantHistory(
    Company $company,
    ?Branch $defaultBranch,
    User $administrator,
    string $prefix,
    array $warehouseDefinitions
): void {
    $category = InventoryCategory::create([
        'company_id' => $company->id,
        'name' => "{$prefix} Grocery",
        'slug' => strtolower($prefix).'-grocery',
        'description' => "Historical category for {$prefix}",
        'sort_order' => 10,
        'is_active' => true,
    ]);
    InventoryCategory::create([
        'company_id' => $company->id,
        'name' => "{$prefix} Household",
        'slug' => strtolower($prefix).'-household',
        'description' => "Second historical category for {$prefix}",
        'sort_order' => 20,
        'is_active' => true,
    ]);
    $unit = InventoryUnit::create([
        'company_id' => $company->id,
        'name' => "{$prefix} Pieces",
        'short_code' => strtolower($prefix).'-pc',
        'type' => 'quantity',
        'decimal_allowed' => false,
        'is_system' => false,
        'is_active' => true,
    ]);
    $taxRate = InventoryTaxRate::create([
        'company_id' => $company->id,
        'name' => "{$prefix} GST 18%",
        'rate' => '18.000',
        'tax_type' => 'gst',
        'country' => 'India',
        'is_default' => true,
        'is_active' => true,
    ]);
    $products = [
        Product::create([
            'company_id' => $company->id,
            'branch_id' => $defaultBranch?->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'tax_rate_id' => $taxRate->id,
            'type' => 'simple',
            'name' => "{$prefix} Historical Tea",
            'slug' => strtolower($prefix).'-historical-tea',
            'sku' => "{$prefix}-TEA-001",
            'barcode' => "{$company->id}000000001",
            'cost_price' => '72.50',
            'selling_price' => '100.00',
            'track_inventory' => true,
            'allow_negative_stock' => false,
            'status' => 'active',
            'is_active' => true,
        ]),
        Product::create([
            'company_id' => $company->id,
            'branch_id' => $defaultBranch?->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'tax_rate_id' => $taxRate->id,
            'type' => 'simple',
            'name' => "{$prefix} Historical Soap",
            'slug' => strtolower($prefix).'-historical-soap',
            'sku' => "{$prefix}-SOAP-002",
            'barcode' => "{$company->id}000000002",
            'cost_price' => '31.25',
            'selling_price' => '50.00',
            'track_inventory' => true,
            'allow_negative_stock' => false,
            'status' => 'active',
            'is_active' => true,
        ]),
    ];
    $warehouses = [];
    $stockLocations = [];
    foreach ($warehouseDefinitions as $index => $definition) {
        $warehouse = Warehouse::create([
            'company_id' => $company->id,
            'branch_id' => $definition['branch']?->id,
            'name' => $definition['name'],
            'code' => $definition['code'],
            'type' => 'store',
            'address_line_1' => $company->address,
            'city' => $company->city,
            'state' => $company->state,
            'country' => 'India',
            'postal_code' => $company->postal_code,
            'is_primary' => $index === 0,
            'is_active' => true,
        ]);
        $warehouses[] = $warehouse;
        $stockLocations[] = StockLocation::create([
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
            'name' => "{$prefix} Historical Bin ".($index + 1),
            'code' => 'HIST-BIN-'.($index + 1),
            'type' => 'bin',
            'aisle' => 'A'.($index + 1),
            'rack' => 'R1',
            'shelf' => 'S1',
            'bin' => 'B1',
            'is_active' => true,
        ]);
    }

    foreach ($products as $index => $product) {
        $warehouseIndex = $index % count($warehouses);
        $warehouse = $warehouses[$warehouseIndex];
        $stockLocation = $stockLocations[$warehouseIndex];
        $quantity = $index === 0 ? '125.000' : '48.500';
        StockLevel::create([
            'company_id' => $company->id,
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'stock_location_id' => $stockLocation->id,
            'product_id' => $product->id,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => $index === 0 ? '5.000' : '3.500',
            'quantity_available' => $index === 0 ? '120.000' : '45.000',
            'last_stock_movement_at' => '2026-06-30 16:00:00',
        ]);
        StockMovement::create([
            'company_id' => $company->id,
            'branch_id' => $warehouse->branch_id,
            'warehouse_id' => $warehouse->id,
            'stock_location_id' => $stockLocation->id,
            'product_id' => $product->id,
            'movement_type' => 'opening_stock',
            'direction' => 'in',
            'quantity' => $quantity,
            'quantity_before' => '0.000',
            'quantity_after' => $quantity,
            'unit_cost' => $product->cost_price,
            'reference_type' => 'historical_fixture',
            'reference_id' => $product->id,
            'reason' => 'Supported pre-Phase-G opening stock',
            'created_by' => $administrator->id,
            'occurred_at' => '2026-06-30 16:00:00',
        ]);
    }

    Customer::create([
        'company_id' => $company->id,
        'branch_id' => $defaultBranch?->id,
        'customer_number' => "{$prefix}-CUS-001",
        'first_name' => "{$prefix} Historical",
        'last_name' => 'Customer',
        'display_name' => "{$prefix} Historical Customer",
        'email' => strtolower($prefix).'-customer@example.test',
        'phone' => '+91-92222222'.str_pad((string) $company->id, 2, '0', STR_PAD_LEFT),
        'customer_type' => 'retail',
        'status' => 'active',
        'source' => 'historical-fixture',
        'billing_address' => $company->address,
        'city' => $company->city,
        'state' => $company->state,
        'country' => 'India',
        'created_by' => $administrator->id,
        'is_active' => true,
    ]);
    Supplier::create([
        'company_id' => $company->id,
        'code' => "{$prefix}-SUP-001",
        'name' => "{$prefix} Historical Supplier",
        'legal_name' => "{$prefix} Historical Supplier Private Limited",
        'display_name' => "{$prefix} Supplier",
        'supplier_type' => 'manufacturer',
        'email' => strtolower($prefix).'-supplier@example.test',
        'phone' => '+91-93333333'.str_pad((string) $company->id, 2, '0', STR_PAD_LEFT),
        'payment_terms' => 'Net 30',
        'default_currency' => 'INR',
        'is_active' => true,
    ]);
    $invoiceCustomer = CrmCustomer::create([
        'company_id' => $company->id,
        'customer_code' => "{$prefix}-CRM-001",
        'company_name' => "{$prefix} Historical Buyer",
        'display_name' => "{$prefix} Buyer",
        'business_type' => 'retail',
        'email' => strtolower($prefix).'-buyer@example.test',
        'phone' => '+91-94444444'.str_pad((string) $company->id, 2, '0', STR_PAD_LEFT),
        'country' => 'India',
        'state' => $company->state,
        'city' => $company->city,
        'billing_address' => $company->address,
        'tax_number' => "{$prefix}BUYERGSTIN",
        'status' => 'active',
        'source' => 'historical-fixture',
        'created_by' => $administrator->id,
        'updated_by' => $administrator->id,
    ]);
    InvoiceTemplateSetting::create([
        'company_id' => $company->id,
        'template_key' => $prefix === 'T1' ? 'structured_gst_grid' : 'classic_business',
        'brand_color' => $prefix === 'T1' ? '#0f766e' : '#1d4ed8',
        'copy_label' => 'original',
        'orientation' => 'portrait',
        'options' => ['show_hsn' => true, 'historical_fixture' => $prefix],
        'updated_by' => $administrator->id,
    ]);

    createInvoices($company, $administrator, $invoiceCustomer, $prefix);
}

function createInvoices(Company $company, User $administrator, CrmCustomer $customer, string $prefix): void
{
    /** @var InvoiceService $service */
    $service = app(InvoiceService::class);
    $definitions = [
        ['label' => 'Draft', 'price' => '100.00', 'quantity' => '1.000', 'discount_type' => 'fixed', 'discount' => '5.00', 'tax' => '18.000', 'rounding' => '0.05'],
        ['label' => 'Issued unpaid', 'price' => '75.00', 'quantity' => '2.000', 'discount_type' => 'percentage', 'discount' => '10.000', 'tax' => '5.000', 'rounding' => '-0.01'],
        ['label' => 'Partially paid', 'price' => '249.99', 'quantity' => '1.000', 'discount_type' => 'fixed', 'discount' => '9.99', 'tax' => '12.000', 'rounding' => '0.03'],
        ['label' => 'Fully paid', 'price' => '500.00', 'quantity' => '1.000', 'discount_type' => 'percentage', 'discount' => '2.500', 'tax' => '18.000', 'rounding' => '-0.02'],
    ];
    $invoices = [];

    foreach ($definitions as $index => $definition) {
        $invoices[] = $service->create($administrator, [
            'customer_id' => $customer->id,
            'billing_name' => $customer->display_name,
            'billing_company' => $customer->company_name,
            'billing_email' => $customer->email,
            'billing_phone' => $customer->phone,
            'billing_address' => $customer->billing_address,
            'billing_country' => 'India',
            'customer_tax_number' => $customer->tax_number,
            'place_of_supply' => $company->state,
            'tax_classification' => 'gst',
            'currency' => 'INR',
            'issue_date' => '2026-07-01',
            'due_date' => '2026-08-01',
            'notes' => "{$prefix} {$definition['label']} historical invoice",
            'adjustment_total' => $definition['rounding'],
            'items' => [[
                'name' => "{$prefix} {$definition['label']} line",
                'description' => 'Synthetic historical item created through InvoiceService',
                'quantity' => $definition['quantity'],
                'unit' => 'unit',
                'unit_price' => $definition['price'],
                'discount_type' => $definition['discount_type'],
                'discount_value' => $definition['discount'],
                'tax_rate' => $definition['tax'],
            ]],
        ]);

        if ($index > 0) {
            $invoices[$index] = $service->issue($invoices[$index], $administrator);
        }
    }

    $service->recordPayment($invoices[2], $administrator, [
        'amount' => '100.00',
        'currency' => 'INR',
        'payment_date' => '2026-07-15',
        'payment_method' => 'bank_transfer',
        'transaction_reference' => "{$prefix}-PARTIAL-PAYMENT",
        'status' => 'cleared',
    ]);
    $service->recordPayment($invoices[3], $administrator, [
        'amount' => (string) $invoices[3]->grand_total,
        'currency' => 'INR',
        'payment_date' => '2026-07-16',
        'payment_method' => 'upi',
        'transaction_reference' => "{$prefix}-FULL-PAYMENT",
        'status' => 'recorded',
    ]);

    // e5f1810 generates receipt ordinals per tenant while enforcing a global
    // receipt-number unique key. Historical imports can contain their own stable
    // receipt formats, so make this fixture's stored receipts tenant-distinct
    // before the next tenant uses the service.
    foreach (DB::table('crm_invoice_payments')->where('company_id', $company->id)->orderBy('id')->get() as $payment) {
        DB::table('crm_invoice_payments')->where('id', $payment->id)->update([
            'receipt_number' => "{$prefix}-{$payment->receipt_number}",
        ]);
    }
}

/** @return array<string, mixed> */
function snapshot(string $stage): array
{
    $phaseG = DB::getSchemaBuilder()->hasTable('branch_user_assignments');
    $tenants = [];

    foreach (rows('companies', ['id']) as $company) {
        $companyId = (int) $company['id'];
        $stockLevels = rowsWhere('stock_levels', $companyId, ['id']);
        $movements = rowsWhere('stock_movements', $companyId, ['id']);
        $invoices = rowsWhere('crm_invoices', $companyId, ['id']);
        $payments = rowsWhere('crm_invoice_payments', $companyId, ['id']);
        $branches = rowsWhere('branches', $companyId, ['id']);
        $warehouses = rowsWhere('warehouses', $companyId, ['id']);

        $tenant = [
            'tenant_id' => $companyId,
            'company_id' => $companyId,
            'company' => selectFields($company, ['id', 'name', 'legal_name', 'tax_id']),
            'users' => selectRows(rowsWhere('users', $companyId, ['id']), ['id', 'company_id', 'branch_id', 'role']),
            'branches' => selectRows($branches, ['id', 'company_id', 'name', 'code', 'is_primary', 'is_active']),
            'warehouses' => selectRows($warehouses, ['id', 'company_id', 'branch_id', 'code', 'is_primary', 'is_active']),
            'categories' => idCount(rowsWhere('inventory_categories', $companyId, ['id'])),
            'products' => idCount(rowsWhere('products', $companyId, ['id'])),
            'customers' => idCount(rowsWhere('customers', $companyId, ['id'])),
            'invoice_customers' => idCount(rowsWhere('crm_customers', $companyId, ['id'])),
            'suppliers' => idCount(rowsWhere('suppliers', $companyId, ['id'])),
            'inventory' => selectRows($stockLevels, [
                'id', 'company_id', 'branch_id', 'warehouse_id', 'stock_location_id', 'product_id',
                'quantity_on_hand', 'quantity_reserved', 'quantity_available',
            ]),
            'consolidated_stock' => stockTotals($stockLevels),
            'stock_ledger' => [
                'ids' => array_map(fn (array $row): int => (int) $row['id'], $movements),
                'entry_count' => count($movements),
                'quantity_total' => decimalSum($movements, 'quantity', 3),
                'signed_quantity_total' => signedMovementTotal($movements),
                'entries' => selectRows($movements, [
                    'id', 'company_id', 'branch_id', 'warehouse_id', 'stock_location_id', 'product_id',
                    'movement_type', 'direction', 'quantity', 'quantity_before', 'quantity_after',
                ]),
            ],
            'invoices' => selectRows($invoices, [
                'id', 'company_id', 'customer_id', 'invoice_number', 'status', 'subtotal', 'discount_total',
                'taxable_total', 'tax_total', 'adjustment_total', 'grand_total', 'amount_paid', 'balance_due',
            ]),
            'payments' => selectRows($payments, [
                'id', 'company_id', 'invoice_id', 'payment_reference', 'amount', 'currency', 'status',
            ]),
            'invoice_template' => selectRows(rowsWhere('invoice_template_settings', $companyId, ['id']), [
                'id', 'company_id', 'template_key', 'brand_color', 'copy_label', 'orientation', 'options',
            ]),
            'tenant_totals' => [
                'invoice_count' => count($invoices),
                'invoice_grand_total' => decimalSum($invoices, 'grand_total', 2),
                'invoice_amount_paid' => decimalSum($invoices, 'amount_paid', 2),
                'invoice_outstanding' => decimalSum($invoices, 'balance_due', 2),
                'payment_count' => count($payments),
                'payment_total' => decimalSum(array_values(array_filter(
                    $payments,
                    fn (array $payment): bool => ! in_array($payment['status'], ['failed', 'pending', 'reversed'], true)
                )), 'amount', 2),
            ],
        ];

        if ($phaseG) {
            $assignments = rowsWhere('branch_user_assignments', $companyId, ['id']);
            $tenant['phase_g'] = [
                'outlets' => selectRows($branches, ['id', 'company_id', 'name', 'code', 'is_primary', 'is_active']),
                'default_outlet_ids' => array_values(array_map(
                    fn (array $branch): int => (int) $branch['id'],
                    array_filter($branches, fn (array $branch): bool => (bool) $branch['is_primary'])
                )),
                'outlet_branch_relationships' => selectRows($branches, ['id', 'company_id']),
                'outlet_warehouse_relationships' => selectRows($warehouses, ['id', 'company_id', 'branch_id']),
                'outlet_user_assignments' => selectRows($assignments, [
                    'id', 'company_id', 'branch_id', 'user_id', 'is_default', 'is_active',
                ]),
                'operational_outlet_links' => [
                    'users' => selectRows(rowsWhere('users', $companyId, ['id']), ['id', 'company_id', 'branch_id']),
                    'products' => selectRows(rowsWhere('products', $companyId, ['id']), ['id', 'company_id', 'branch_id']),
                    'warehouses' => selectRows($warehouses, ['id', 'company_id', 'branch_id']),
                    'stock_levels' => selectRows($stockLevels, ['id', 'company_id', 'branch_id']),
                    'stock_movements' => selectRows($movements, ['id', 'company_id', 'branch_id']),
                ],
                'outlet_scoped_stock' => outletStockTotals($branches, $stockLevels),
                'unassigned_stock' => stockTotals(array_values(array_filter(
                    $stockLevels,
                    fn (array $level): bool => $level['branch_id'] === null
                ))),
            ];
        }

        $tenants[] = $tenant;
    }

    return [
        'metadata' => [
            'stage' => $stage,
            'pre_phase_g_commit' => EXPECTED_PRE_PHASE_G,
            'phase_g_commit' => EXPECTED_PHASE_G,
            'database' => 'temporary-sqlite',
        ],
        'tenants' => $tenants,
        'integrity_check' => DB::selectOne('PRAGMA integrity_check')->integrity_check ?? null,
    ];
}

function verify(string $beforePath, string $afterPath, string $retryOutput): void
{
    $before = readSnapshot($beforePath);
    $after = readSnapshot($afterPath);

    assertSameValue('tenant count', count($before['tenants']), count($after['tenants']));
    foreach ($before['tenants'] as $index => $beforeTenant) {
        $afterTenant = $after['tenants'][$index] ?? null;
        if (! is_array($afterTenant)) {
            mismatch("tenants.{$index}", $beforeTenant, $afterTenant);
        }

        assertProtectedTenant($beforeTenant, $afterTenant, "tenants.{$index}");
        assertOutletBoundaries($afterTenant, "tenants.{$index}");
    }
    assertSameValue('after.integrity_check', 'ok', $after['integrity_check'] ?? null);

    $idempotencyBefore = idempotencyProjection($after);
    reapplySafeOutletBackfill();
    $retry = snapshot('retry');
    writeSnapshot($retryOutput, $retry);
    assertSameValue('idempotency retry projection', $idempotencyBefore, idempotencyProjection($retry));
    assertSameValue('retry.integrity_check', 'ok', $retry['integrity_check'] ?? null);

    foreach ($retry['tenants'] as $index => $retryTenant) {
        assertOutletBoundaries($retryTenant, "retry.tenants.{$index}");
    }

    printSuccess($before, $after);
}

/** @param array<string, mixed> $before @param array<string, mixed> $after */
function assertProtectedTenant(array $before, array $after, string $path): void
{
    foreach (['tenant_id', 'company_id', 'company', 'categories', 'products', 'customers', 'invoice_customers', 'suppliers',
        'inventory', 'consolidated_stock', 'stock_ledger', 'invoices', 'payments', 'invoice_template', 'tenant_totals'] as $field) {
        assertSameValue("{$path}.{$field}", $before[$field] ?? null, $after[$field] ?? null);
    }

    assertSameValue(
        "{$path}.user identities and roles",
        selectRows($before['users'], ['id', 'company_id', 'role']),
        selectRows($after['users'], ['id', 'company_id', 'role'])
    );
    assertSameValue("{$path}.warehouse records", $before['warehouses'], $after['warehouses']);

    $beforeBranchIds = array_map(fn (array $row): int => (int) $row['id'], $before['branches']);
    $preservedBranches = array_values(array_filter(
        $after['branches'],
        fn (array $row): bool => in_array((int) $row['id'], $beforeBranchIds, true)
    ));
    assertSameValue("{$path}.existing branches", $before['branches'], $preservedBranches);
}

/** @param array<string, mixed> $tenant */
function assertOutletBoundaries(array $tenant, string $path): void
{
    $phaseG = $tenant['phase_g'] ?? null;
    if (! is_array($phaseG)) {
        throw new RuntimeException("{$path}.phase_g is missing.");
    }

    assertSameValue("{$path}.default outlet count", 1, count($phaseG['default_outlet_ids']));
    $companyId = (int) $tenant['company_id'];
    $outletCompany = [];
    foreach ($phaseG['outlets'] as $outlet) {
        $outletCompany[(int) $outlet['id']] = (int) $outlet['company_id'];
    }

    foreach ($phaseG['outlet_user_assignments'] as $assignment) {
        assertSameValue("{$path}.assignment {$assignment['id']} company", $companyId, (int) $assignment['company_id']);
        assertSameValue(
            "{$path}.assignment {$assignment['id']} outlet company",
            $companyId,
            $outletCompany[(int) $assignment['branch_id']] ?? null
        );
    }
    foreach ($phaseG['operational_outlet_links'] as $type => $records) {
        foreach ($records as $record) {
            assertSameValue("{$path}.{$type}.{$record['id']}.company_id", $companyId, (int) $record['company_id']);
            if ($record['branch_id'] !== null) {
                assertSameValue(
                    "{$path}.{$type}.{$record['id']}.branch tenant",
                    $companyId,
                    $outletCompany[(int) $record['branch_id']] ?? null
                );
            }
        }
    }

    $scopedOnHand = array_sum(array_map(
        fn (array $totals): int => decimalToMinor($totals['quantity_on_hand'], 3),
        $phaseG['outlet_scoped_stock']
    ));
    $unassignedOnHand = decimalToMinor($phaseG['unassigned_stock']['quantity_on_hand'], 3);
    assertSameValue(
        "{$path}.outlet plus unassigned consolidated stock",
        decimalToMinor($tenant['consolidated_stock']['quantity_on_hand'], 3),
        $scopedOnHand + $unassignedOnHand
    );
}

function reapplySafeOutletBackfill(): void
{
    foreach (DB::table('companies')->orderBy('id')->cursor() as $company) {
        $primary = DB::table('branches')
            ->where('company_id', $company->id)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();

        if (! $primary) {
            DB::table('branches')->insert([
                'company_id' => $company->id,
                'name' => 'Main Outlet',
                'legal_name' => $company->legal_name ?? $company->name,
                'code' => 'MAIN',
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'city' => $company->city,
                'state' => $company->state,
                'postal_code' => $company->postal_code,
                'country' => $company->country ?? 'India',
                'country_code' => 'IN',
                'tax_number' => $company->tax_id,
                'timezone' => $company->timezone,
                'currency' => $company->currency,
                'is_primary' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $primary = DB::table('branches')
                ->where('company_id', $company->id)
                ->where('code', 'MAIN')
                ->first();
        }

        DB::table('branches')->where('company_id', $company->id)->where('id', '!=', $primary->id)->update(['is_primary' => false]);
        DB::table('branches')->where('id', $primary->id)->update(['is_primary' => true, 'is_active' => true]);
        DB::table('users')->where('company_id', $company->id)->whereNull('branch_id')->update(['branch_id' => $primary->id]);
    }
}

/** @param array<string, mixed> $snapshot @return array<string, mixed> */
function idempotencyProjection(array $snapshot): array
{
    return [
        'protected' => array_map(fn (array $tenant): array => [
            'tenant_id' => $tenant['tenant_id'],
            'company_id' => $tenant['company_id'],
            'users' => $tenant['users'],
            'branches' => $tenant['branches'],
            'warehouses' => $tenant['warehouses'],
            'categories' => $tenant['categories'],
            'products' => $tenant['products'],
            'customers' => $tenant['customers'],
            'suppliers' => $tenant['suppliers'],
            'inventory' => $tenant['inventory'],
            'stock_ledger' => $tenant['stock_ledger'],
            'invoices' => $tenant['invoices'],
            'payments' => $tenant['payments'],
            'invoice_template' => $tenant['invoice_template'],
            'phase_g' => $tenant['phase_g'],
        ], $snapshot['tenants']),
    ];
}

/** @param array<string, mixed> $before @param array<string, mixed> $after */
function printSuccess(array $before, array $after): void
{
    $counts = [
        'tenants' => count($after['tenants']),
        'users' => 0,
        'outlets' => 0,
        'warehouses' => 0,
        'categories' => 0,
        'products' => 0,
        'customers' => 0,
        'suppliers' => 0,
        'stock_levels' => 0,
        'stock_ledger_entries' => 0,
        'invoices' => 0,
        'payments' => 0,
        'invoice_templates' => 0,
    ];
    foreach ($after['tenants'] as $tenant) {
        $counts['users'] += count($tenant['users']);
        $counts['outlets'] += count($tenant['phase_g']['outlets']);
        $counts['warehouses'] += count($tenant['warehouses']);
        $counts['categories'] += $tenant['categories']['count'];
        $counts['products'] += $tenant['products']['count'];
        $counts['customers'] += $tenant['customers']['count'];
        $counts['suppliers'] += $tenant['suppliers']['count'];
        $counts['stock_levels'] += count($tenant['inventory']);
        $counts['stock_ledger_entries'] += $tenant['stock_ledger']['entry_count'];
        $counts['invoices'] += count($tenant['invoices']);
        $counts['payments'] += count($tenant['payments']);
        $counts['invoice_templates'] += count($tenant['invoice_template']);
    }

    echo "Phase G historical migration verification PASSED.\n";
    foreach ($counts as $entity => $count) {
        echo "  {$entity}: {$count}\n";
    }
    echo "  protected historical fields: preserved\n";
    echo "  tenant boundaries: preserved\n";
    echo "  default outlet rule: exactly one per tenant\n";
    echo "  safe backfill retry: idempotent\n";
    echo "  SQLite integrity_check: ok\n";
}

/** @return array<int, array<string, mixed>> */
function rows(string $table, array $orderBy): array
{
    $query = DB::table($table);
    foreach ($orderBy as $column) {
        $query->orderBy($column);
    }

    return array_map(fn (object $row): array => normalizeRow((array) $row), $query->get()->all());
}

/** @return array<int, array<string, mixed>> */
function rowsWhere(string $table, int $companyId, array $orderBy): array
{
    $query = DB::table($table)->where('company_id', $companyId);
    foreach ($orderBy as $column) {
        $query->orderBy($column);
    }

    return array_map(fn (object $row): array => normalizeRow((array) $row), $query->get()->all());
}

/** @param array<string, mixed> $row @return array<string, mixed> */
function normalizeRow(array $row): array
{
    foreach ($row as $key => $value) {
        if (is_string($value) && preg_match('/^-?\d+\.\d+$/', $value)) {
            $row[$key] = normalizeDecimal($value);
        }
    }

    return $row;
}

/** @param array<string, mixed> $row @param array<int, string> $fields @return array<string, mixed> */
function selectFields(array $row, array $fields): array
{
    $selected = [];
    foreach ($fields as $field) {
        $selected[$field] = $row[$field] ?? null;
    }

    return $selected;
}

/** @param array<int, array<string, mixed>> $rows @param array<int, string> $fields @return array<int, array<string, mixed>> */
function selectRows(array $rows, array $fields): array
{
    return array_map(fn (array $row): array => selectFields($row, $fields), $rows);
}

/** @param array<int, array<string, mixed>> $rows @return array{ids: array<int, int>, count: int} */
function idCount(array $rows): array
{
    return [
        'ids' => array_map(fn (array $row): int => (int) $row['id'], $rows),
        'count' => count($rows),
    ];
}

/** @param array<int, array<string, mixed>> $levels @return array<string, string> */
function stockTotals(array $levels): array
{
    return [
        'quantity_on_hand' => decimalSum($levels, 'quantity_on_hand', 3),
        'quantity_reserved' => decimalSum($levels, 'quantity_reserved', 3),
        'quantity_available' => decimalSum($levels, 'quantity_available', 3),
    ];
}

/** @param array<int, array<string, mixed>> $branches @param array<int, array<string, mixed>> $levels @return array<int, array<string, mixed>> */
function outletStockTotals(array $branches, array $levels): array
{
    return array_map(function (array $branch) use ($levels): array {
        $branchLevels = array_values(array_filter(
            $levels,
            fn (array $level): bool => (int) $level['branch_id'] === (int) $branch['id']
        ));

        return ['outlet_id' => (int) $branch['id']] + stockTotals($branchLevels);
    }, $branches);
}

/** @param array<int, array<string, mixed>> $rows */
function decimalSum(array $rows, string $field, int $scale): string
{
    $minor = array_sum(array_map(
        fn (array $row): int => decimalToMinor((string) ($row[$field] ?? '0'), $scale),
        $rows
    ));

    return minorToDecimal($minor, $scale);
}

/** @param array<int, array<string, mixed>> $movements */
function signedMovementTotal(array $movements): string
{
    $minor = 0;
    foreach ($movements as $movement) {
        $quantity = decimalToMinor((string) $movement['quantity'], 3);
        $minor += $movement['direction'] === 'out' ? -$quantity : $quantity;
    }

    return minorToDecimal($minor, 3);
}

function decimalToMinor(string $value, int $scale): int
{
    if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
        throw new RuntimeException("Invalid decimal value: {$value}");
    }
    $fraction = substr(str_pad($matches[3] ?? '', $scale, '0'), 0, $scale);
    $minor = ((int) $matches[2] * (10 ** $scale)) + (int) $fraction;

    return ($matches[1] ?? '') === '-' ? -$minor : $minor;
}

function minorToDecimal(int $minor, int $scale): string
{
    $sign = $minor < 0 ? '-' : '';
    $minor = abs($minor);
    $factor = 10 ** $scale;

    return $sign.intdiv($minor, $factor).'.'.str_pad((string) ($minor % $factor), $scale, '0', STR_PAD_LEFT);
}

function normalizeDecimal(string $value): string
{
    $value = rtrim(rtrim($value, '0'), '.');

    return $value === '' || $value === '-0' ? '0' : $value;
}

/** @param array<string, mixed> $snapshot */
function writeSnapshot(string $path, array $snapshot): void
{
    $json = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($path, $json.PHP_EOL, LOCK_EX) === false) {
        throw new RuntimeException("Unable to write snapshot: {$path}");
    }
}

/** @return array<string, mixed> */
function readSnapshot(string $path): array
{
    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException("Unable to read snapshot: {$path}");
    }

    return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
}

function assertSameValue(string $path, mixed $before, mixed $after): void
{
    if ($before !== $after) {
        mismatch($path, $before, $after);
    }
}

function mismatch(string $path, mixed $before, mixed $after): never
{
    $beforeJson = json_encode($before, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $afterJson = json_encode($after, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    throw new RuntimeException("Mismatch at {$path}: before={$beforeJson}; after={$afterJson}");
}

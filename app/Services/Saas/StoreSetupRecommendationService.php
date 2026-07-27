<?php

namespace App\Services\Saas;

use App\Models\Company;
use App\Services\Crm\InvoiceTemplateService;

class StoreSetupRecommendationService
{
    public function __construct(private readonly EntitlementService $entitlements) {}

    /** @param array<string,mixed> $answers @return array<string,mixed> */
    public function make(Company $company, array $answers): array
    {
        if (! config('store_setup.recommendations_enabled')) {
            return ['version' => config('store_setup.version'), 'categories' => [], 'tax' => ['rates' => [], 'reason' => 'Recommendations are disabled for this environment.'], 'invoice_template' => ['key' => 'structured_gst_grid', 'name' => 'Structured GST Grid', 'reason' => 'Choose a design later from Invoice Designs.'], 'barcode' => ['enabled' => false, 'generate_missing' => false, 'reason' => 'Configure barcode preferences later.'], 'modules' => [], 'product_entry' => ['method' => 'choose_later', 'reason' => 'Choose your product-entry method later.'], 'next_steps' => ['Add a product', 'Add a customer']];
        }
        $industry = (string) ($answers['industry'] ?? $company->industry ?? 'general_retail');
        $printer = (string) ($answers['printer']['type'] ?? 'unsure');
        $volume = (string) ($answers['product_volume'] ?? 'unsure');
        $scanner = (string) ($answers['scanner']['choice'] ?? 'unsure');
        $template = config('store_setup.invoice_templates.'.$printer, 'structured_gst_grid');

        return [
            'version' => config('store_setup.version'),
            'categories' => collect(config('store_setup.categories.'.$industry, config('store_setup.categories.default')))
                ->map(fn (string $name) => ['name' => $name, 'reason' => 'A small starter set for your selected business type.', 'optional' => true])->values()->all(),
            'tax' => [
                'rates' => array_values(array_filter((array) data_get($answers, 'tax.rates', []), fn ($rate) => in_array((string) $rate, ['0', '5', '12', '18', '28'], true))),
                'reason' => data_get($answers, 'tax.registered') ? 'These are the GST rates you confirmed for your store.' : 'No GST tax rates will be created until you confirm registration.',
            ],
            'invoice_template' => [
                'key' => $template,
                'name' => app(InvoiceTemplateService::class)->definitions()[$template]['name'] ?? 'Structured GST Grid',
                'reason' => match ($printer) { 'thermal' => 'Compact, detailed output suits receipt-printer workflows.', 'digital' => 'Balanced layout is suitable for secure digital delivery.', default => 'A GST-ready A4 design is a safe starting point for your printing choice.' },
            ],
            'barcode' => [
                'enabled' => in_array($scanner, ['already_have', 'plan_to_buy'], true),
                'generate_missing' => (bool) data_get($answers, 'scanner.generate_missing', false),
                'reason' => in_array($scanner, ['already_have', 'plan_to_buy'], true) ? 'Barcode search and label-printing guidance are relevant to your scanner choice.' : 'Manual product search remains available; no barcode preference will be applied.',
            ],
            'modules' => $this->modules($company, $industry),
            'product_entry' => [
                'method' => match ($volume) { 'under_50' => 'manual_or_template', '50_250', '251_1000' => 'csv_template', '1001_5000', 'over_5000' => 'bulk_import_boundary', default => 'choose_later' },
                'reason' => match ($volume) { 'under_50' => 'A small catalogue is usually fastest to create manually.', '50_250', '251_1000' => 'A CSV template will reduce repetitive entry.', '1001_5000', 'over_5000' => 'Prepare the CSV template now; validated bulk import will arrive in a dedicated future release.', default => 'You can choose manual entry or the product template later.' },
            ],
            'next_steps' => ['Add a product', 'Add a customer', 'Create your first invoice'],
        ];
    }

    /** @return array<int,array{key:string,label:string,reason:string,locked:bool}> */
    private function modules(Company $company, string $industry): array
    {
        $base = [
            ['key' => 'pos.billing', 'label' => 'POS billing', 'reason' => 'Core counter billing for retail.', 'locked' => ! $this->entitlements->allows($company, 'pos.billing')],
            ['key' => 'inventory.basic', 'label' => 'Basic inventory', 'reason' => 'Track products and stock.', 'locked' => ! $this->entitlements->allows($company, 'inventory.basic')],
            ['key' => 'customers.basic', 'label' => 'Customers', 'reason' => 'Keep customer details for billing.', 'locked' => ! $this->entitlements->allows($company, 'customers.basic')],
        ];
        if (in_array($industry, ['grocery_supermarket', 'pharmacy'], true)) $base[] = ['key' => 'purchases', 'label' => 'Purchase management', 'reason' => 'Useful for supplier-led stock intake.', 'locked' => true];
        if ($industry === 'pharmacy') $base[] = ['key' => 'batch_expiry', 'label' => 'Batch and expiry tracking', 'reason' => 'Requires an enabled batch/expiry capability.', 'locked' => true];
        return $base;
    }
}

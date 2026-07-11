<?php

namespace App\Services\Inventory;

use App\Models\Inventory\BarcodeLabelTemplate;
use App\Models\Inventory\BarcodePrintBatch;
use App\Models\Inventory\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BarcodeService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveTemplate(User $user, array $data, ?BarcodeLabelTemplate $template = null): BarcodeLabelTemplate
    {
        return DB::transaction(function () use ($user, $data, $template): BarcodeLabelTemplate {
            $payload = $this->templatePayload($user, $data);

            if (($payload['is_default'] ?? false) === true) {
                BarcodeLabelTemplate::query()
                    ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $user->company_id))
                    ->when($template, fn ($query) => $query->whereKeyNot($template->id))
                    ->update(['is_default' => false]);
            }

            $model = $template
                ? tap($template)->update($payload)
                : BarcodeLabelTemplate::create($payload);

            $this->auditLogger->record($template ? 'inventory.barcode_template.updated' : 'inventory.barcode_template.created', $model, 'Barcode label template saved');

            return $model->refresh();
        });
    }

    public function setDefault(User $user, BarcodeLabelTemplate $template): BarcodeLabelTemplate
    {
        BarcodeLabelTemplate::query()
            ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $user->company_id))
            ->update(['is_default' => false]);

        $template->update(['is_default' => true, 'is_active' => true]);
        $this->auditLogger->record('inventory.barcode_template.defaulted', $template, 'Barcode label template set as default');

        return $template;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPrintBatch(User $user, array $data): BarcodePrintBatch
    {
        return DB::transaction(function () use ($user, $data): BarcodePrintBatch {
            $batch = BarcodePrintBatch::create([
                'company_id' => $user->company_id,
                'template_id' => $data['template_id'],
                'batch_number' => 'BC-'.now()->format('Ymd').'-'.Str::upper(Str::random(5)),
                'title' => $data['title'] ?? null,
                'created_by' => $user->id,
                'status' => 'draft',
                'total_labels' => collect($data['items'])->sum(fn (array $item) => (int) $item['quantity']),
            ]);

            foreach ($data['items'] as $item) {
                $product = Product::query()->where('company_id', $user->company_id)->findOrFail($item['product_id']);
                $batch->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price_override' => $item['price_override'] ?? null,
                    'label_data' => [
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'barcode' => $product->barcode,
                        'price' => $item['price_override'] ?? $product->selling_price,
                    ],
                ]);
            }

            $this->auditLogger->record('inventory.barcode.print_batch_created', $batch, 'Barcode print batch created');

            return $batch->load(['template', 'items.product']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function templatePayload(User $user, array $data): array
    {
        $bools = [
            'show_product_name',
            'show_sku',
            'show_barcode_text',
            'show_price',
            'show_mrp',
            'show_offer_price',
            'show_brand',
            'show_category',
            'show_size',
            'show_color',
            'show_batch',
            'show_expiry',
            'show_company_name',
            'show_logo',
            'is_default',
            'is_active',
        ];

        foreach ($bools as $field) {
            $data[$field] = (bool) ($data[$field] ?? false);
        }

        $data['company_id'] = $data['company_id'] ?? $user->company_id;

        return $data;
    }
}

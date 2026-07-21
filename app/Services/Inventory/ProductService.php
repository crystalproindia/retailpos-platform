<?php

namespace App\Services\Inventory;

use App\Events\Domain\Inventory\ProductCreated;
use App\Events\Domain\Inventory\ProductUpdated;
use App\Models\Inventory\Product;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use App\Services\Saas\UsageService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
        private readonly UsageService $usage,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Product
    {
        return DB::transaction(function () use ($user, $data): Product {
            $this->usage->assertWithinLimit($user->company, 'products');
            $product = Product::create($this->payload($user, $data) + [
                'company_id' => $user->company_id,
                'branch_id' => $data['branch_id'] ?? $user->branch_id,
            ]);

            $this->syncVariantAttributes($product, $data['attribute_value_ids'] ?? []);
            $this->auditLogger->record('inventory.product.created', $product, 'Inventory product created');
            $this->domainEvents->dispatch(new ProductCreated(
                companyId: $product->company_id,
                actorId: $user->id,
                aggregateType: Product::class,
                aggregateId: $product->id,
                payload: $this->eventPayload($product),
            ));

            return $product->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, User $user, array $data): Product
    {
        return DB::transaction(function () use ($product, $user, $data): Product {
            $product->update($this->payload($user, $data, $product));
            $this->syncVariantAttributes($product, $data['attribute_value_ids'] ?? []);

            $this->auditLogger->record('inventory.product.updated', $product, 'Inventory product updated');
            $this->domainEvents->dispatch(new ProductUpdated(
                companyId: $product->company_id,
                actorId: $user->id,
                aggregateType: Product::class,
                aggregateId: $product->id,
                payload: $this->eventPayload($product->refresh()),
            ));

            return $product;
        });
    }

    public function delete(Product $product): void
    {
        $product->delete();
        $this->auditLogger->record('inventory.product.deleted', $product, 'Inventory product moved to trash');
    }

    public function restore(Product $product): Product
    {
        $product->restore();
        $this->auditLogger->record('inventory.product.restored', $product, 'Inventory product restored');

        return $product;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(User $user, array $data, ?Product $product = null): array
    {
        $payload = Arr::only($data, [
            'category_id',
            'brand_id',
            'unit_id',
            'tax_rate_id',
            'parent_product_id',
            'type',
            'name',
            'sku',
            'barcode',
            'hsn_code',
            'description',
            'short_description',
            'cost_price',
            'selling_price',
            'mrp',
            'wholesale_price',
            'online_price',
            'purchase_price',
            'variant_name',
            'image',
            'status',
        ]);

        $payload['slug'] = Str::slug($data['slug'] ?? $data['name'] ?? $product?->name ?? Str::uuid()->toString());
        $payload['track_inventory'] = (bool) ($data['track_inventory'] ?? false);
        $payload['allow_negative_stock'] = (bool) ($data['allow_negative_stock'] ?? false);
        $payload['has_variants'] = (bool) ($data['has_variants'] ?? false);
        $payload['is_variant'] = (bool) ($data['is_variant'] ?? false);
        $payload['is_active'] = ($payload['status'] ?? Product::STATUS_ACTIVE) === Product::STATUS_ACTIVE;

        if (($payload['parent_product_id'] ?? null) && ! ($data['is_variant'] ?? false)) {
            $payload['is_variant'] = true;
        }

        if (($payload['is_variant'] ?? false) && empty($payload['type'])) {
            $payload['type'] = 'variant';
        }

        $payload['branch_id'] = $data['branch_id'] ?? $product?->branch_id ?? $user->branch_id;

        return $payload;
    }

    /**
     * @param  array<int, int|string>  $attributeValueIds
     */
    private function syncVariantAttributes(Product $product, array $attributeValueIds): void
    {
        $sync = collect($attributeValueIds)
            ->filter()
            ->mapWithKeys(fn ($valueId) => [(int) $valueId => ['attribute_id' => \App\Models\Inventory\ProductAttributeValue::query()->whereKey($valueId)->value('attribute_id')]])
            ->filter(fn (array $pivot) => $pivot['attribute_id'] !== null)
            ->all();

        $product->attributeValues()->sync($sync);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Product $product): array
    {
        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'barcode' => $product->barcode,
            'status' => $product->status,
            'is_variant' => $product->is_variant,
            'parent_product_id' => $product->parent_product_id,
        ];
    }
}

<?php

namespace App\Http\Requests\Inventory;

use App\Models\Inventory\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->isMethod('PUT') ? 'inventory.products.update' : 'inventory.products.create';
        if (! $this->user()?->can($permission)) {
            return false;
        }

        return ! ($this->hasFile('product_image') || $this->boolean('remove_image'))
            || $this->user()->can('inventory.products.image.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = $this->user()?->company_id;
        $productId = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->where('company_id', $companyId)->ignore($productId)],
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->where('company_id', $companyId)->ignore($productId)],
            'barcode' => ['nullable', 'string', 'max:255'],
            'hsn_code' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:inventory_categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:inventory_brands,id'],
            'unit_id' => ['required', 'integer', 'exists:inventory_units,id'],
            'tax_rate_id' => ['nullable', 'integer', 'exists:inventory_tax_rates,id'],
            'parent_product_id' => ['nullable', 'integer', 'exists:products,id'],
            'type' => ['required', 'string', Rule::in(['simple', 'variant_parent', 'variant', 'service'])],
            'variant_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'mrp' => ['nullable', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'online_price' => ['nullable', 'numeric', 'min:0'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'pack_size' => ['nullable', 'numeric', 'gt:0'],
            'product_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:min_width=32,min_height=32,max_width=4000,max_height=4000'],
            'remove_image' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in([Product::STATUS_ACTIVE, Product::STATUS_INACTIVE])],
            'track_inventory' => ['nullable', 'boolean'],
            'track_batches' => ['nullable', 'boolean'],
            'track_serials' => ['nullable', 'boolean'],
            'track_expiry' => ['nullable', 'boolean'],
            'allow_negative_stock' => ['nullable', 'boolean'],
            'has_variants' => ['nullable', 'boolean'],
            'is_variant' => ['nullable', 'boolean'],
            'attribute_value_ids' => ['nullable', 'array'],
            'attribute_value_ids.*' => ['nullable', 'integer', 'exists:product_attribute_values,id'],
        ];
    }
}

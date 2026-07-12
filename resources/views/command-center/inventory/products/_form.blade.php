@csrf
@if ($product->exists)
    @method('PUT')
@endif

<div class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
    <section class="product-form-card">
        <div class="mb-6"><h2 class="product-form-heading">Product details</h2><p class="product-form-help">Start with the details your team will use to find and identify this product.</p></div>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="space-y-2 md:col-span-2">
                <span class="text-sm font-medium">Product name</span>
                <input name="name" value="{{ old('name', $product->name) }}" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
            </label>
            <label class="space-y-2">
                <span class="text-sm font-medium">SKU</span>
                <input name="sku" value="{{ old('sku', $product->sku) }}" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
            </label>
            <label class="space-y-2">
                <span class="text-sm font-medium">Barcode</span>
                <input name="barcode" value="{{ old('barcode', $product->barcode) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
            </label>
            <label class="space-y-2">
                <span class="text-sm font-medium">Category</span>
                <select name="category_id" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Unassigned</option>
                    @foreach ($options['categories'] as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2">
                <span class="text-sm font-medium">Brand</span>
                <select name="brand_id" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Unassigned</option>
                    @foreach ($options['brands'] as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2">
                <span class="text-sm font-medium">Unit</span>
                <select name="unit_id" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($options['units'] as $unit)
                        <option value="{{ $unit->id }}" @selected(old('unit_id', $product->unit_id) == $unit->id)>{{ $unit->name }} ({{ $unit->short_code }})</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2">
                <span class="text-sm font-medium">Tax rate</span>
                <select name="tax_rate_id" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">None</option>
                    @foreach ($options['taxRates'] as $taxRate)
                        <option value="{{ $taxRate->id }}" @selected(old('tax_rate_id', $product->tax_rate_id) == $taxRate->id)>{{ $taxRate->name }} ({{ $taxRate->rate }}%)</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2">
                <span class="text-sm font-medium">Type</span>
                <select name="type" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    @foreach (['simple' => 'Simple', 'variant_parent' => 'Variant parent', 'variant' => 'Variant', 'service' => 'Service'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $product->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-2">
                <span class="text-sm font-medium">Status</span>
                <select name="status" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="active" @selected(old('status', $product->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $product->status) === 'inactive')>Inactive</option>
                </select>
            </label>
            <label class="space-y-2">
                <span class="text-sm font-medium">HSN code</span>
                <input name="hsn_code" value="{{ old('hsn_code', $product->hsn_code) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
            </label>
            <label class="space-y-2 md:col-span-2">
                <span class="text-sm font-medium">Description</span>
                <textarea name="description" rows="4" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">{{ old('description', $product->description) }}</textarea>
            </label>
        </div>
    </section>

    <aside class="space-y-4">
        <section class="product-form-card">
            <h2 class="product-form-heading">Pricing</h2><p class="product-form-help">Set the prices used across purchasing, selling, and online channels.</p>
            <div class="mt-4 grid gap-4">
                @foreach (['cost_price' => 'Cost price', 'selling_price' => 'Selling price', 'mrp' => 'MRP', 'wholesale_price' => 'Wholesale', 'online_price' => 'Online', 'purchase_price' => 'Purchase'] as $field => $label)
                    <label class="space-y-1">
                        <span class="text-sm font-medium">{{ $label }}</span>
                        <input name="{{ $field }}" type="number" step="0.01" min="0" value="{{ old($field, $product->{$field}) }}" @required($field === 'selling_price') class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    </label>
                @endforeach
            </div>
        </section>

        <section class="product-form-card">
            <h2 class="product-form-heading">Inventory behavior</h2><p class="product-form-help">Choose how stock and variants should behave for this product.</p>
            <div class="mt-4 space-y-3">
                @foreach (['track_inventory' => 'Track inventory', 'allow_negative_stock' => 'Allow negative stock', 'has_variants' => 'Has variants', 'is_variant' => 'This is a variant'] as $field => $label)
                    <label class="flex min-h-11 items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-medium text-slate-700">
                        <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $product->{$field})) class="rounded border-slate-300 text-teal-600">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        @if ($options['attributes']->isNotEmpty())
            <section class="product-form-card">
                <h2 class="product-form-heading">Variant attributes</h2><p class="product-form-help">Link this product to the attribute values your catalog already uses.</p>
                <div class="mt-4 space-y-3">
                    @foreach ($options['attributes'] as $attribute)
                        <label class="block space-y-1">
                            <span class="text-sm font-medium">{{ $attribute->name }}</span>
                            <select name="attribute_value_ids[]" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                                <option value="">Not selected</option>
                                @foreach ($attribute->values as $value)
                                    <option value="{{ $value->id }}" @selected($product->attributeValues->contains('id', $value->id))>{{ $value->value }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="sticky bottom-3 z-10 rounded-xl border border-slate-200 bg-white/95 p-3 shadow-[0_-8px_28px_rgb(15_23_42_/_0.09)] backdrop-blur"><button class="w-full rounded-lg bg-teal-600 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-teal-700">Save product</button></div>
    </aside>
</div>

@if ($errors->any())
    <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100">
        {{ $errors->first() }}
    </div>
@endif

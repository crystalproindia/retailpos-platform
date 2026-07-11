@csrf
@if ($product->exists)
    @method('PUT')
@endif

<div class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-4 md:grid-cols-2">
            <label class="space-y-1 md:col-span-2">
                <span class="text-sm font-medium">Product name</span>
                <input name="name" value="{{ old('name', $product->name) }}" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
            </label>
            <label class="space-y-1">
                <span class="text-sm font-medium">SKU</span>
                <input name="sku" value="{{ old('sku', $product->sku) }}" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
            </label>
            <label class="space-y-1">
                <span class="text-sm font-medium">Barcode</span>
                <input name="barcode" value="{{ old('barcode', $product->barcode) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
            </label>
            <label class="space-y-1">
                <span class="text-sm font-medium">Category</span>
                <select name="category_id" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Unassigned</option>
                    @foreach ($options['categories'] as $category)
                        <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-1">
                <span class="text-sm font-medium">Brand</span>
                <select name="brand_id" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Unassigned</option>
                    @foreach ($options['brands'] as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id', $product->brand_id) == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-1">
                <span class="text-sm font-medium">Unit</span>
                <select name="unit_id" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($options['units'] as $unit)
                        <option value="{{ $unit->id }}" @selected(old('unit_id', $product->unit_id) == $unit->id)>{{ $unit->name }} ({{ $unit->short_code }})</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-1">
                <span class="text-sm font-medium">Tax rate</span>
                <select name="tax_rate_id" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="">None</option>
                    @foreach ($options['taxRates'] as $taxRate)
                        <option value="{{ $taxRate->id }}" @selected(old('tax_rate_id', $product->tax_rate_id) == $taxRate->id)>{{ $taxRate->name }} ({{ $taxRate->rate }}%)</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-1">
                <span class="text-sm font-medium">Type</span>
                <select name="type" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    @foreach (['simple' => 'Simple', 'variant_parent' => 'Variant parent', 'variant' => 'Variant', 'service' => 'Service'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $product->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="space-y-1">
                <span class="text-sm font-medium">Status</span>
                <select name="status" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    <option value="active" @selected(old('status', $product->status) === 'active')>Active</option>
                    <option value="inactive" @selected(old('status', $product->status) === 'inactive')>Inactive</option>
                </select>
            </label>
            <label class="space-y-1">
                <span class="text-sm font-medium">HSN code</span>
                <input name="hsn_code" value="{{ old('hsn_code', $product->hsn_code) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
            </label>
            <label class="space-y-1 md:col-span-2">
                <span class="text-sm font-medium">Description</span>
                <textarea name="description" rows="4" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">{{ old('description', $product->description) }}</textarea>
            </label>
        </div>
    </section>

    <aside class="space-y-4">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Pricing</h2>
            <div class="mt-4 grid gap-4">
                @foreach (['cost_price' => 'Cost price', 'selling_price' => 'Selling price', 'mrp' => 'MRP', 'wholesale_price' => 'Wholesale', 'online_price' => 'Online', 'purchase_price' => 'Purchase'] as $field => $label)
                    <label class="space-y-1">
                        <span class="text-sm font-medium">{{ $label }}</span>
                        <input name="{{ $field }}" type="number" step="0.01" min="0" value="{{ old($field, $product->{$field}) }}" @required($field === 'selling_price') class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    </label>
                @endforeach
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Inventory behavior</h2>
            <div class="mt-4 space-y-3">
                @foreach (['track_inventory' => 'Track inventory', 'allow_negative_stock' => 'Allow negative stock', 'has_variants' => 'Has variants', 'is_variant' => 'This is a variant'] as $field => $label)
                    <label class="flex items-center gap-3 text-sm">
                        <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $product->{$field})) class="rounded border-slate-300 text-teal-600">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        @if ($options['attributes']->isNotEmpty())
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Variant attributes</h2>
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

        <button class="w-full rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950">Save product</button>
    </aside>
</div>

@if ($errors->any())
    <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100">
        {{ $errors->first() }}
    </div>
@endif

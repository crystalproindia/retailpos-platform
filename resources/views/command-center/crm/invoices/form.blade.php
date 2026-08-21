@extends('layouts.admin')

@section('title', $invoice ? 'Edit Draft Invoice' : 'Create Invoice')
@section('page-title', $invoice ? 'Edit Draft Invoice' : 'Create Invoice')

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        @include('command-center.crm.partials.nav')

        @if ($quotation)
            <form method="POST" action="{{ route('sales.invoices.store-from-quotation', $quotation) }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                @csrf
                <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Create invoice from accepted quotation</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">This copies customer-facing terms and immutable item snapshots from {{ $quotation->quotation_number }}. Internal quotation notes are not copied.</p>
                <div class="mt-5 rounded-lg bg-slate-50 p-4 text-sm dark:bg-slate-950"><p class="font-semibold text-slate-950 dark:text-white">{{ $quotation->customer_company ?: $quotation->customer_name }}</p><p class="mt-1 text-slate-500">{{ $quotation->currency }} {{ number_format((float) $quotation->grand_total, 2) }} · {{ $quotation->items->count() }} items</p></div>
                <button class="mt-5 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Confirm and create draft invoice</button>
            </form>
        @else
            @php
                $formItems = old('items', $invoice?->items?->map(fn ($item) => ['product_id'=>$item->product_id, 'name' => $item->name, 'description' => $item->description, 'quantity' => $item->quantity, 'unit' => $item->unit, 'unit_price' => $item->unit_price, 'discount_type' => $item->discount_type, 'discount_value' => $item->discount_value, 'tax_rate' => $item->tax_rate])->all() ?? [['product_id'=>'', 'name' => '', 'description' => '', 'quantity' => '1', 'unit' => 'service', 'unit_price' => '0', 'discount_type' => 'fixed', 'discount_value' => '0', 'tax_rate' => '0']]);
                $selectedCustomer ??= $invoice?->customer;
                $selectedCustomerPayload = $selectedCustomer ? [
                    'id' => $selectedCustomer->id,
                    'name' => $selectedCustomer->display_name,
                    'company_name' => $selectedCustomer->company_name,
                    'phone' => $selectedCustomer->phone,
                    'email' => $selectedCustomer->email,
                    'tax_number' => $selectedCustomer->tax_number,
                    'billing_address' => $selectedCustomer->billing_address,
                    'country' => $selectedCustomer->country,
                    'business_type' => $selectedCustomer->business_type,
                    'outstanding' => null,
                ] : null;
            @endphp
            <form method="POST" action="{{ $invoice ? route('sales.invoices.update', $invoice) : route('sales.invoices.store') }}" class="space-y-6" data-product-search-url="{{ route('sales.invoices.products.search') }}">
                @csrf
                @if ($invoice) @method('PUT') @endif
                <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">{{ $invoice ? 'Edit draft invoice' : 'New sales invoice' }}</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $invoice ? 'Update this draft before issuing it. Issued invoices are protected from silent commercial changes.' : 'Add the billing details and line items. RetailPOS calculates totals on the server when you save.' }}</p>
                    @if ($errors->any())<div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">Please review the highlighted information and try again.</div>@endif
                    <div class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950" data-customer-selector data-search-url="{{ route('sales.invoices.customers.search') }}" data-create-url="{{ route('sales.invoices.customers.store') }}" data-selected='@json($selectedCustomerPayload)'>
                        <input type="hidden" name="customer_id" value="{{ old('customer_id', $selectedCustomer?->id) }}" data-customer-id>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div><h2 class="text-sm font-semibold text-slate-950 dark:text-white">Customer</h2><p class="mt-1 text-xs leading-5 text-slate-500">Search an existing CRM customer, create one here, or continue as a walk-in invoice.</p></div>
                            @can('crm.customers.create')<button type="button" data-new-customer class="inline-flex min-h-11 items-center justify-center rounded-lg border border-teal-300 bg-white px-4 text-sm font-semibold text-teal-800 transition hover:bg-teal-50 dark:border-teal-800 dark:bg-slate-900 dark:text-teal-200">+ New Customer</button>@endcan
                        </div>
                        <div class="mt-4" data-customer-search-wrap>
                            <label class="text-sm font-medium" for="invoice-customer-search">Find customer</label>
                            <div class="mt-1 flex gap-2"><input id="invoice-customer-search" data-customer-search autocomplete="off" placeholder="Name, phone, email or GSTIN" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-900"><button type="button" data-clear-customer class="min-h-11 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">Walk-in</button></div>
                            <div data-customer-results class="mt-2 hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900"></div>
                        </div>
                        <div data-selected-customer class="mt-4 hidden rounded-lg border border-teal-200 bg-white p-4 dark:border-teal-900 dark:bg-slate-900">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><p data-customer-name class="truncate font-semibold text-slate-950 dark:text-white"></p><p data-customer-company class="mt-1 truncate text-sm text-slate-500"></p></div><span class="rounded-full bg-teal-100 px-2.5 py-1 text-xs font-semibold text-teal-800 dark:bg-teal-950 dark:text-teal-200">Selected</span></div>
                            <div class="mt-3 grid gap-2 text-xs text-slate-600 sm:grid-cols-2 dark:text-slate-300"><p data-customer-phone></p><p data-customer-tax></p><p data-customer-address class="sm:col-span-2"></p><p data-customer-outstanding class="font-semibold text-amber-700 dark:text-amber-300"></p></div>
                        </div>
                        <p data-customer-feedback class="mt-2 hidden text-sm" role="status" aria-live="polite"></p>
                    </div>
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-medium">Billing name<input name="billing_name" value="{{ old('billing_name', $invoice?->billing_name ?? $selectedCustomer?->display_name) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Company<input name="billing_company" value="{{ old('billing_company', $invoice?->billing_company ?? $selectedCustomer?->company_name) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Email<input name="billing_email" type="email" value="{{ old('billing_email', $invoice?->billing_email ?? $selectedCustomer?->email) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Phone<input name="billing_phone" value="{{ old('billing_phone', $invoice?->billing_phone ?? $selectedCustomer?->phone) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Currency<input name="currency" required maxlength="3" value="{{ old('currency', $invoice?->currency ?? 'INR') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Due date<input name="due_date" type="date" value="{{ old('due_date', $invoice?->due_date?->toDateString()) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Customer tax number<input name="customer_tax_number" value="{{ old('customer_tax_number', $invoice?->customer_tax_number ?? $selectedCustomer?->tax_number) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Place of supply<input name="place_of_supply" value="{{ old('place_of_supply', $invoice?->place_of_supply) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                    </div>
                    @php($taxMode = old('tax_mode', $invoice?->tax_mode ?? 'gst'))
                    <section class="mt-6 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950" data-tax-mode>
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-semibold text-slate-950 dark:text-white">Tax mode</h2><p class="mt-1 text-sm text-slate-500">The saved document decides tax calculations. No-GST sets all line taxes to zero on the server when your GST Settings permit it.</p></div><div class="inline-flex rounded-lg border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-900"><label><input class="sr-only" type="radio" name="tax_mode" value="gst" @checked($taxMode === 'gst')><span class="block rounded-md px-4 py-2 text-sm font-semibold has-[:checked]:bg-slate-950 has-[:checked]:text-white">GST</span></label><label><input class="sr-only" type="radio" name="tax_mode" value="no_gst" @checked($taxMode === 'no_gst')><span class="block rounded-md px-4 py-2 text-sm font-semibold has-[:checked]:bg-slate-950 has-[:checked]:text-white">No-GST</span></label></div></div>
                    </section>
                    <label class="mt-4 block text-sm font-medium">Billing address<textarea name="billing_address" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">{{ old('billing_address', $invoice?->billing_address ?? $selectedCustomer?->billing_address) }}</textarea></label>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-semibold text-slate-950 dark:text-white">Line items</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use Add item for additional services. Totals are recalculated on save.</p></div><button type="button" data-add-invoice-item class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Add item</button></div>
                    <div data-invoice-items class="mt-5 space-y-4">
                        @foreach ($formItems as $index => $item)
                            <div data-invoice-item class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                                <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item['product_id'] ?? '' }}" data-product-id>
                                <div class="mb-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-950" data-product-selector>
                                    <div class="flex items-center justify-between gap-3"><label class="min-w-0 flex-1 text-xs font-semibold uppercase text-slate-500">Product <span class="normal-case font-normal">Optional</span><input data-product-search autocomplete="off" value="{{ ($item['product_id'] ?? null) ? $item['name'] : '' }}" placeholder="Search name, SKU or barcode" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 normal-case text-sm dark:border-slate-700 dark:bg-slate-900"></label><button type="button" data-clear-product class="mt-5 shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">Clear</button></div>
                                    <p data-product-status class="mt-2 text-xs text-slate-500">{{ ($item['product_id'] ?? null) ? 'Product linked. Server snapshots its cost only when the draft is saved.' : 'Leave blank for a free-text service or custom line.' }}</p><div data-product-results class="mt-2 hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900"></div>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <label class="text-xs font-semibold uppercase text-slate-500">Item<input name="items[{{ $index }}][name]" required value="{{ $item['name'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label>
                                    <label class="text-xs font-semibold uppercase text-slate-500">Quantity<input name="items[{{ $index }}][quantity]" required type="number" min="0.001" step="0.001" value="{{ $item['quantity'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label>
                                    <label class="text-xs font-semibold uppercase text-slate-500">Unit price<input name="items[{{ $index }}][unit_price]" required type="number" min="0" step="0.01" value="{{ $item['unit_price'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label>
                                    <label class="text-xs font-semibold uppercase text-slate-500">Tax %<input name="items[{{ $index }}][tax_rate]" type="number" min="0" max="100" step="0.001" value="{{ $item['tax_rate'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label>
                                    <label class="text-xs font-semibold uppercase text-slate-500">Unit<input name="items[{{ $index }}][unit]" value="{{ $item['unit'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label>
                                    <label class="text-xs font-semibold uppercase text-slate-500">Discount type<select name="items[{{ $index }}][discount_type]" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"><option value="fixed" @selected($item['discount_type'] === 'fixed')>Fixed</option><option value="percentage" @selected($item['discount_type'] === 'percentage')>Percentage</option></select></label>
                                    <label class="text-xs font-semibold uppercase text-slate-500">Discount value<input name="items[{{ $index }}][discount_value]" type="number" min="0" step="0.001" value="{{ $item['discount_value'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label>
                                    <div class="flex items-end"><button type="button" data-remove-invoice-item class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700">Remove</button></div>
                                </div>
                                <label class="mt-3 block text-xs font-semibold uppercase text-slate-500">Description<textarea name="items[{{ $index }}][description]" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm">{{ $item['description'] }}</textarea></label>
                            </div>
                        @endforeach
                    </div>
                    <label class="mt-4 block max-w-xs text-sm font-medium">Adjustment<input name="adjustment_total" type="number" step="0.01" value="{{ old('adjustment_total', $invoice?->adjustment_total ?? '0') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                </section>

                <section class="grid gap-6 lg:grid-cols-2">
                    <label class="rounded-lg border border-slate-200 bg-white p-5 text-sm font-medium shadow-sm dark:border-slate-800 dark:bg-slate-900">Customer notes<textarea name="notes" rows="5" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">{{ old('notes', $invoice?->notes) }}</textarea><span class="mt-2 block text-xs font-normal text-slate-500">Visible on the customer invoice.</span></label>
                    <label class="rounded-lg border border-slate-200 bg-white p-5 text-sm font-medium shadow-sm dark:border-slate-800 dark:bg-slate-900">Terms and conditions<textarea name="terms_conditions" rows="5" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2.5">{{ old('terms_conditions', $invoice?->terms_conditions) }}</textarea><span class="mt-2 block text-xs font-normal text-slate-500">Visible on the customer invoice.</span></label>
                </section>
                <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-white p-4 text-sm font-medium shadow-sm dark:border-slate-800 dark:bg-slate-900"><input type="hidden" name="show_authorized_signature" value="0"><input type="checkbox" name="show_authorized_signature" value="1" @checked(old('show_authorized_signature', $invoice?->show_authorized_signature ?? true))>Show authorized signature on this document</label>

                <div class="flex justify-end"><button class="rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">{{ $invoice ? 'Save draft changes' : 'Save draft' }}</button></div>
            </form>
        @endif
    </div>

    @if (! $quotation)
        <div data-customer-modal class="fixed inset-0 z-[70] hidden" role="dialog" aria-modal="true" aria-labelledby="new-customer-title"><button type="button" data-close-customer-modal class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" aria-label="Close new customer panel"></button><section class="absolute inset-y-0 right-0 flex w-full max-w-lg flex-col overflow-y-auto bg-white p-5 shadow-2xl dark:bg-slate-900"><div class="flex items-start justify-between gap-4"><div><h2 id="new-customer-title" class="text-lg font-semibold text-slate-950 dark:text-white">New customer</h2><p class="mt-1 text-sm text-slate-500">Add the essentials now. You can complete the profile later.</p></div><button type="button" data-close-customer-modal class="flex size-11 items-center justify-center rounded-lg border border-slate-200" aria-label="Close"><x-icon name="x" class="size-5" /></button></div><div class="mt-6 space-y-4" data-quick-customer-fields><label class="block text-sm font-medium">Customer name <span class="text-rose-600">*</span><input data-quick-field="name" class="mt-1 w-full rounded-lg border-slate-300 text-sm" required></label><label class="block text-sm font-medium">Business name<input data-quick-field="company_name" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label><div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm font-medium">Phone<input data-quick-field="phone" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label><label class="block text-sm font-medium">Email<input data-quick-field="email" type="email" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label></div><label class="block text-sm font-medium">GSTIN<input data-quick-field="tax_number" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label><label class="block text-sm font-medium">Billing address<textarea data-quick-field="billing_address" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea></label><details class="rounded-lg border border-slate-200 p-4"><summary class="cursor-pointer text-sm font-semibold">More details</summary><div class="mt-4 space-y-4"><label class="block text-sm font-medium">Customer type<input data-quick-field="business_type" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></label><label class="block text-sm font-medium">Internal notes<textarea data-quick-field="notes" rows="3" class="mt-1 w-full rounded-lg border-slate-300 text-sm"></textarea></label></div></details><p data-quick-customer-error class="hidden rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"></p><button type="button" data-save-customer class="w-full rounded-lg bg-teal-600 px-4 py-3 text-sm font-semibold text-white hover:bg-teal-700">Create and select customer</button></div></section></div>
        <template id="invoice-item-template"><div data-invoice-item class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"><input type="hidden" data-field="product_id" data-product-id><div class="mb-3 rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-950" data-product-selector><div class="flex items-center justify-between gap-3"><label class="min-w-0 flex-1 text-xs font-semibold uppercase text-slate-500">Product <span class="normal-case font-normal">Optional</span><input data-product-search autocomplete="off" placeholder="Search name, SKU or barcode" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 normal-case text-sm dark:border-slate-700 dark:bg-slate-900"></label><button type="button" data-clear-product class="mt-5 shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">Clear</button></div><p data-product-status class="mt-2 text-xs text-slate-500">Leave blank for a free-text service or custom line.</p><div data-product-results class="mt-2 hidden overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900"></div></div><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><label class="text-xs font-semibold uppercase text-slate-500">Item<input data-field="name" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label><label class="text-xs font-semibold uppercase text-slate-500">Quantity<input data-field="quantity" required type="number" min="0.001" step="0.001" value="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label><label class="text-xs font-semibold uppercase text-slate-500">Unit price<input data-field="unit_price" required type="number" min="0" step="0.01" value="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label><label class="text-xs font-semibold uppercase text-slate-500">Tax %<input data-field="tax_rate" type="number" min="0" max="100" step="0.001" value="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label><label class="text-xs font-semibold uppercase text-slate-500">Unit<input data-field="unit" value="service" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label><label class="text-xs font-semibold uppercase text-slate-500">Discount type<select data-field="discount_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"><option value="fixed">Fixed</option><option value="percentage">Percentage</option></select></label><label class="text-xs font-semibold uppercase text-slate-500">Discount value<input data-field="discount_value" type="number" min="0" step="0.001" value="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label><div class="flex items-end"><button type="button" data-remove-invoice-item class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700">Remove</button></div></div><label class="mt-3 block text-xs font-semibold uppercase text-slate-500">Description<textarea data-field="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></textarea></label></div></template>
        <script>
            document.addEventListener('click', function (event) {
                const items = document.querySelector('[data-invoice-items]');
                if (!items) return;
                if (event.target.closest('[data-add-invoice-item]')) {
                    const index = items.querySelectorAll('[data-invoice-item]').length;
                    const fragment = document.getElementById('invoice-item-template').content.cloneNode(true);
                    fragment.querySelectorAll('[data-field]').forEach(function (field) { field.name = 'items[' + index + '][' + field.dataset.field + ']'; });
                    items.appendChild(fragment);
                }
                const remove = event.target.closest('[data-remove-invoice-item]');
                if (remove && items.querySelectorAll('[data-invoice-item]').length > 1) remove.closest('[data-invoice-item]').remove();
            });

            (function () {
                const form = document.querySelector('[data-product-search-url]'); if (!form) return;
                let timer, controller;
                const clear = (row) => { row.querySelector('[data-product-id]').value = ''; row.querySelector('[data-product-search]').value = ''; row.querySelector('[data-product-status]').textContent = 'Leave blank for a free-text service or custom line.'; row.querySelector('[data-product-results]').classList.add('hidden'); };
                form.addEventListener('click', (event) => { const button = event.target.closest('[data-clear-product]'); if (button) clear(button.closest('[data-invoice-item]')); });
                form.addEventListener('input', (event) => { if (!event.target.matches('[data-product-search]')) return; const input=event.target, row=input.closest('[data-invoice-item]'), results=row.querySelector('[data-product-results]'); clearTimeout(timer); if (input.value.trim().length < 2) { results.classList.add('hidden'); return; } timer=setTimeout(async()=>{ controller?.abort(); controller=new AbortController(); row.querySelector('[data-product-status]').textContent='Searching products…'; try { const response=await fetch(form.dataset.productSearchUrl+'?q='+encodeURIComponent(input.value.trim()),{headers:{Accept:'application/json'},signal:controller.signal}); const data=response.ok?await response.json():{products:[]}; results.innerHTML=data.products.length?data.products.map((p)=>`<button type="button" data-product-option='${JSON.stringify(p).replace(/'/g,'&#039;')}' class="block w-full border-b border-slate-100 px-3 py-2 text-left text-sm hover:bg-teal-50 dark:border-slate-800 dark:hover:bg-slate-800"><span class="font-semibold">${p.name}</span><span class="ml-2 text-xs text-slate-500">${p.sku||'No SKU'} · ${p.selling_price}</span></button>`).join(''):'<p class="px-3 py-3 text-sm text-slate-500">No matching products.</p>'; results.classList.remove('hidden'); row.querySelector('[data-product-status]').textContent='Select a product or keep this as free text.'; } catch (error) { if(error.name!=='AbortError') row.querySelector('[data-product-status]').textContent='Product search is unavailable. You can still use a free-text line.'; } },250); });
                form.addEventListener('click', (event) => { const option=event.target.closest('[data-product-option]'); if (!option) return; const p=JSON.parse(option.dataset.productOption), row=option.closest('[data-invoice-item]'); row.querySelector('[data-product-id]').value=p.id; row.querySelector('[data-product-search]').value=p.name; row.querySelector('[data-product-status]').textContent='Product linked. The server verifies it and captures profitability only on save.'; row.querySelector('[data-product-results]').classList.add('hidden'); const name=row.querySelector('[name$="[name]"]'), price=row.querySelector('[name$="[unit_price]"]'); if(name) name.value=p.name; if(price && (!price.value || price.value==='0')) price.value=p.selling_price; });
            })();

            (function () {
                const root = document.querySelector('[data-customer-selector]');
                if (!root) return;
                const input = root.querySelector('[data-customer-search]');
                const results = root.querySelector('[data-customer-results]');
                const selected = root.querySelector('[data-selected-customer]');
                const customerId = root.querySelector('[data-customer-id]');
                const modal = document.querySelector('[data-customer-modal]');
                let timer;

                const escape = (value) => String(value ?? '').replace(/[&<>'"]/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));
                const fill = (customer, updateBilling = true) => {
                    customerId.value = customer?.id || '';
                    selected.classList.toggle('hidden', !customer);
                    if (!customer) return;
                    selected.querySelector('[data-customer-name]').textContent = customer.name || customer.company_name;
                    selected.querySelector('[data-customer-company]').textContent = customer.company_name || '';
                    selected.querySelector('[data-customer-phone]').textContent = customer.phone || customer.email || 'No phone or email recorded';
                    selected.querySelector('[data-customer-tax]').textContent = customer.tax_number ? 'GSTIN ' + customer.tax_number : 'GSTIN not recorded';
                    selected.querySelector('[data-customer-address]').textContent = customer.billing_address || 'Billing address not recorded';
                    selected.querySelector('[data-customer-outstanding]').textContent = customer.outstanding !== null && customer.outstanding !== undefined ? 'Outstanding: INR ' + Number(customer.outstanding).toFixed(2) : '';
                    if (updateBilling) {
                        const values = {billing_name: customer.name, billing_company: customer.company_name, billing_email: customer.email, billing_phone: customer.phone, billing_address: customer.billing_address, billing_country: customer.country, customer_tax_number: customer.tax_number};
                        Object.entries(values).forEach(([name, value]) => { const field = document.querySelector('[name="' + name + '"]'); if (field) field.value = value || ''; });
                    }
                };
                fill(root.dataset.selected ? JSON.parse(root.dataset.selected) : null, false);

                input.addEventListener('input', () => {
                    clearTimeout(timer);
                    if (input.value.trim().length < 2) { results.classList.add('hidden'); return; }
                    timer = setTimeout(async () => {
                        const response = await fetch(root.dataset.searchUrl + '?q=' + encodeURIComponent(input.value.trim()), {headers: {'Accept':'application/json'}});
                        const data = response.ok ? await response.json() : {customers: []};
                        results.innerHTML = data.customers.length ? data.customers.map((customer) => '<button type="button" data-customer-option=\'' + escape(JSON.stringify(customer)) + '\' class="block w-full border-b border-slate-100 px-4 py-3 text-left last:border-0 hover:bg-teal-50"><span class="block font-semibold text-slate-900">' + escape(customer.company_name || customer.name) + '</span><span class="mt-1 block text-xs text-slate-500">' + escape([customer.phone, customer.email, customer.tax_number].filter(Boolean).join(' · ') || 'No contact details') + '</span></button>').join('') : '<p class="px-4 py-5 text-sm text-slate-500">No matching customers.</p>';
                        results.classList.remove('hidden');
                    }, 250);
                });
                results.addEventListener('click', (event) => { const option = event.target.closest('[data-customer-option]'); if (option) { fill(JSON.parse(option.dataset.customerOption)); results.classList.add('hidden'); input.value = ''; } });
                root.querySelector('[data-clear-customer]').addEventListener('click', () => fill(null));
                document.querySelector('[data-new-customer]')?.addEventListener('click', () => { modal.classList.remove('hidden'); document.body.classList.add('overflow-hidden'); modal.querySelector('[data-quick-field="name"]').focus(); });
                modal?.querySelectorAll('[data-close-customer-modal]').forEach((button) => button.addEventListener('click', () => { modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); }));
                document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) { modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); } });
                modal?.querySelector('[data-save-customer]')?.addEventListener('click', async (event) => {
                    const button = event.currentTarget; const error = modal.querySelector('[data-quick-customer-error]'); const payload = {};
                    modal.querySelectorAll('[data-quick-field]').forEach((field) => payload[field.dataset.quickField] = field.value);
                    button.disabled = true; error.classList.add('hidden');
                    const response = await fetch(root.dataset.createUrl, {method:'POST', headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:JSON.stringify(payload)});
                    const data = await response.json(); button.disabled = false;
                    if (!response.ok) { error.textContent = data.message || Object.values(data.errors || {}).flat()[0] || 'Customer could not be created.'; error.classList.remove('hidden'); return; }
                    fill(data.customer); modal.classList.add('hidden'); document.body.classList.remove('overflow-hidden'); modal.querySelectorAll('[data-quick-field]').forEach((field) => field.value = '');
                });
            })();
        </script>
    @endif
@endsection

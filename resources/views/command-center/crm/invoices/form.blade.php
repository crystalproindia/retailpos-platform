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
                $formItems = old('items', $invoice?->items?->map(fn ($item) => ['name' => $item->name, 'description' => $item->description, 'quantity' => $item->quantity, 'unit' => $item->unit, 'unit_price' => $item->unit_price, 'discount_type' => $item->discount_type, 'discount_value' => $item->discount_value, 'tax_rate' => $item->tax_rate])->all() ?? [['name' => '', 'description' => '', 'quantity' => '1', 'unit' => 'service', 'unit_price' => '0', 'discount_type' => 'fixed', 'discount_value' => '0', 'tax_rate' => '0']]);
            @endphp
            <form method="POST" action="{{ $invoice ? route('sales.invoices.update', $invoice) : route('sales.invoices.store') }}" class="space-y-6">
                @csrf
                @if ($invoice) @method('PUT') @endif
                <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">{{ $invoice ? 'Edit draft invoice' : 'New sales invoice' }}</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $invoice ? 'Update this draft before issuing it. Issued invoices are protected from silent commercial changes.' : 'Add the billing details and line items. RetailPOS calculates totals on the server when you save.' }}</p>
                    @if ($errors->any())<div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">Please review the highlighted information and try again.</div>@endif
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-medium">Billing name<input name="billing_name" value="{{ old('billing_name', $invoice?->billing_name) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Company<input name="billing_company" value="{{ old('billing_company', $invoice?->billing_company) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Email<input name="billing_email" type="email" value="{{ old('billing_email', $invoice?->billing_email) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Phone<input name="billing_phone" value="{{ old('billing_phone', $invoice?->billing_phone) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Currency<input name="currency" required maxlength="3" value="{{ old('currency', $invoice?->currency ?? 'INR') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Due date<input name="due_date" type="date" value="{{ old('due_date', $invoice?->due_date?->toDateString()) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Customer tax number<input name="customer_tax_number" value="{{ old('customer_tax_number', $invoice?->customer_tax_number) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                        <label class="text-sm font-medium">Place of supply<input name="place_of_supply" value="{{ old('place_of_supply', $invoice?->place_of_supply) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5"></label>
                    </div>
                    <label class="mt-4 block text-sm font-medium">Billing address<textarea name="billing_address" rows="3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5">{{ old('billing_address', $invoice?->billing_address) }}</textarea></label>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="font-semibold text-slate-950 dark:text-white">Line items</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use Add item for additional services. Totals are recalculated on save.</p></div><button type="button" data-add-invoice-item class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Add item</button></div>
                    <div data-invoice-items class="mt-5 space-y-4">
                        @foreach ($formItems as $index => $item)
                            <div data-invoice-item class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
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

                <div class="flex justify-end"><button class="rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">{{ $invoice ? 'Save draft changes' : 'Save draft' }}</button></div>
            </form>
        @endif
    </div>

    @if (! $quotation)
        <template id="invoice-item-template"><div data-invoice-item class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"><label class="text-xs font-semibold uppercase text-slate-500">Item<input data-field="name" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label><label class="text-xs font-semibold uppercase text-slate-500">Quantity<input data-field="quantity" required type="number" min="0.001" step="0.001" value="1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label><label class="text-xs font-semibold uppercase text-slate-500">Unit price<input data-field="unit_price" required type="number" min="0" step="0.01" value="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label><label class="text-xs font-semibold uppercase text-slate-500">Tax %<input data-field="tax_rate" type="number" min="0" max="100" step="0.001" value="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label><label class="text-xs font-semibold uppercase text-slate-500">Unit<input data-field="unit" value="service" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label><label class="text-xs font-semibold uppercase text-slate-500">Discount type<select data-field="discount_type" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"><option value="fixed">Fixed</option><option value="percentage">Percentage</option></select></label><label class="text-xs font-semibold uppercase text-slate-500">Discount value<input data-field="discount_value" type="number" min="0" step="0.001" value="0" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></label><div class="flex items-end"><button type="button" data-remove-invoice-item class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700">Remove</button></div></div><label class="mt-3 block text-xs font-semibold uppercase text-slate-500">Description<textarea data-field="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 normal-case text-sm"></textarea></label></div></template>
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
        </script>
    @endif
@endsection

@extends('layouts.admin')

@section('title', 'Create Proforma Invoice')
@section('page-title', 'Create Proforma Invoice')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><a href="{{ route('crm.proformas.index') }}">Proforma Invoices</a><span>/</span><span>Create</span>
@endsection

@section('content')
    @php
        $taxMode = old('tax_mode', $quotation->tax_mode ?? 'gst');
        $formItems = old('items', $items);
        $selectedCustomerId = old('customer_id', $customerId);
    @endphp

    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <form method="POST" action="{{ route('crm.proformas.store') }}" class="space-y-6" data-proforma-form>
            @csrf
            <input type="hidden" name="lead_id" value="{{ old('lead_id', $leadId) }}">
            <input type="hidden" name="quotation_id" value="{{ old('quotation_id', $quotation->id) }}">

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                    <div>
                        <p class="text-sm font-medium text-teal-700 dark:text-teal-300">CRM commercial document</p>
                        <h1 class="mt-1 text-xl font-semibold text-slate-950 dark:text-white">Create proforma invoice</h1>
                        <p class="mt-2 max-w-2xl text-sm text-slate-500 dark:text-slate-400">Choose an authorized customer, add the proposed products or services, then save a draft. Final totals and document settings are calculated and captured securely when you save.</p>
                    </div>
                    <a href="{{ route('crm.proformas.index') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Back to proformas</a>
                </div>

                @if ($errors->any())
                    <div class="mt-5 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-950 dark:bg-rose-950/30 dark:text-rose-100">Please correct the highlighted fields before creating this proforma.</div>
                @endif

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Customer record
                        <select name="customer_id" data-proforma-customer-select class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <option value="">Use the billing contact entered below</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected((int) $selectedCustomerId === $customer->id) data-name="{{ $customer->display_name }}" data-company="{{ $customer->company_name }}" data-email="{{ $customer->email }}" data-phone="{{ $customer->phone }}" data-address="{{ $customer->billing_address }}">{{ $customer->company_name ?: $customer->display_name }}{{ $customer->display_name && $customer->company_name ? ' · '.$customer->display_name : '' }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-xs font-normal text-slate-500 dark:text-slate-400">Only customers available to your tenant and role are listed. Selecting one copies its current billing details for review.</span>
                        @error('customer_id')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Internal reference
                        <input name="title" required value="{{ old('title', $quotation->title ?: 'Proforma Invoice') }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <span class="mt-1 block text-xs font-normal text-slate-500 dark:text-slate-400">For your team. The customer-facing document heading remains PROFORMA INVOICE.</span>
                        @error('title')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror
                    </label>
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Customer name<input name="customer_name" data-proforma-customer-field="name" value="{{ old('customer_name', $quotation->customer_name) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@error('customer_name')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Company<input name="customer_company" data-proforma-customer-field="company" value="{{ old('customer_company', $quotation->customer_company) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@error('customer_company')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Email<input name="customer_email" type="email" data-proforma-customer-field="email" value="{{ old('customer_email', $quotation->customer_email) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@error('customer_email')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Phone<input name="customer_phone" data-proforma-customer-field="phone" value="{{ old('customer_phone', $quotation->customer_phone) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@error('customer_phone')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Invoice date<input name="invoice_date" type="date" required value="{{ old('invoice_date', $quotation->invoice_date?->format('Y-m-d') ?? now()->toDateString()) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@error('invoice_date')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Due / validity date<input name="due_date" type="date" value="{{ old('due_date', $quotation->due_date?->format('Y-m-d')) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@error('due_date')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="text-sm font-semibold text-slate-700 dark:text-slate-200">Currency<input name="currency" maxlength="3" required value="{{ old('currency', $quotation->currency ?? 'INR') }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 uppercase text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@error('currency')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="md:col-span-2 text-sm font-semibold text-slate-700 dark:text-slate-200">Billing address<textarea name="billing_address" rows="3" data-proforma-customer-field="address" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('billing_address', $quotation->billing_address) }}</textarea>@error('billing_address')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                </div>

                <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950">
                    <h2 class="font-semibold text-slate-950 dark:text-white">Tax mode</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">No-GST proformas are available only where the company’s compliance configuration permits them. The server recalculates every total when the draft is created.</p>
                    <div class="mt-3 inline-flex rounded-lg border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-900">
                        <label><input class="sr-only" type="radio" name="tax_mode" value="gst" @checked($taxMode === 'gst')><span class="block rounded-md px-4 py-2 text-sm font-semibold has-[:checked]:bg-slate-950 has-[:checked]:text-white dark:has-[:checked]:bg-teal-300 dark:has-[:checked]:text-slate-950">GST proforma</span></label>
                        <label><input class="sr-only" type="radio" name="tax_mode" value="no_gst" @checked($taxMode === 'no_gst')><span class="block rounded-md px-4 py-2 text-sm font-semibold has-[:checked]:bg-slate-950 has-[:checked]:text-white dark:has-[:checked]:bg-teal-300 dark:has-[:checked]:text-slate-950">No-GST proforma</span></label>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Products and services</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use a line for each product or service. The totals below are an estimate; the saved document uses the authoritative server calculation.</p></div><button type="button" data-proforma-add-item class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Add line</button></div>
                <div data-proforma-items class="mt-5 space-y-4">
                    @foreach ($formItems as $index => $item)
                        <div data-proforma-item class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                            <div class="grid gap-3 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1.25fr)_100px_140px_130px_110px_auto]">
                                <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Item name<input name="items[{{ $index }}][name]" required value="{{ $item['name'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                                <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Description<input name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                                <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Quantity<input data-proforma-quantity name="items[{{ $index }}][quantity]" type="number" min="0.001" step="0.001" required value="{{ $item['quantity'] ?? 1 }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                                <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Unit price<input data-proforma-unit-price name="items[{{ $index }}][unit_price]" type="number" min="0" step="0.01" required value="{{ $item['unit_price'] ?? 0 }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                                <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Discount<input data-proforma-discount name="items[{{ $index }}][discount_amount]" type="number" min="0" step="0.01" value="{{ $item['discount_amount'] ?? 0 }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                                <label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Tax %<input data-proforma-tax-rate name="items[{{ $index }}][tax_rate]" type="number" min="0" max="100" step="0.001" value="{{ $item['tax_rate'] ?? 0 }}" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                                <button type="button" data-proforma-remove-item class="self-end rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300">Remove</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <template data-proforma-item-template><div data-proforma-item class="rounded-lg border border-slate-200 p-4 dark:border-slate-800"><div class="grid gap-3 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1.25fr)_100px_140px_130px_110px_auto]"><label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Item name<input name="items[__INDEX__][name]" required class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label><label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Description<input name="items[__INDEX__][description]" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label><label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Quantity<input data-proforma-quantity name="items[__INDEX__][quantity]" type="number" min="0.001" step="0.001" required value="1" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label><label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Unit price<input data-proforma-unit-price name="items[__INDEX__][unit_price]" type="number" min="0" step="0.01" required value="0" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label><label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Discount<input data-proforma-discount name="items[__INDEX__][discount_amount]" type="number" min="0" step="0.01" value="0" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label><label class="text-xs font-semibold text-slate-500 dark:text-slate-400">Tax %<input data-proforma-tax-rate name="items[__INDEX__][tax_rate]" type="number" min="0" max="100" step="0.001" value="0" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label><button type="button" data-proforma-remove-item class="self-end rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300">Remove</button></div></div></template>
                <div class="mt-5 ml-auto max-w-sm space-y-2 rounded-lg bg-slate-50 p-4 text-sm dark:bg-slate-950"><div class="flex justify-between text-slate-500 dark:text-slate-400"><span>Subtotal</span><span data-proforma-subtotal>0.00</span></div><div class="flex justify-between text-slate-500 dark:text-slate-400"><span>Discount</span><span data-proforma-discount-total>0.00</span></div><div class="flex justify-between text-slate-500 dark:text-slate-400"><span>Tax</span><span data-proforma-tax-total>0.00</span></div><div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold text-slate-950 dark:border-slate-800 dark:text-white"><span>Grand total</span><span data-proforma-grand-total>0.00</span></div></div>
            </section>

            <section class="grid gap-6 xl:grid-cols-2">
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="text-base font-semibold text-slate-950 dark:text-white">Notes and terms</h2><label class="mt-5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Customer-facing notes<textarea name="notes" rows="5" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('notes', $quotation->notes) }}</textarea></label><label class="mt-5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Terms and conditions<textarea name="terms_conditions" rows="7" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('terms_conditions', $quotation->terms_conditions) }}</textarea></label></article>
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="text-base font-semibold text-slate-950 dark:text-white">Payment and document settings</h2><p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Payment account details, template selection, watermark, and branding use the active company configuration and are snapshotted when this proforma is created. Later changes will not alter this document.</p><label class="mt-5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Internal remarks<textarea name="internal_remarks" rows="5" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('internal_remarks') }}</textarea></label><label class="mt-5 flex items-center gap-3 text-sm font-semibold text-slate-700 dark:text-slate-200"><input type="hidden" name="show_authorized_signature" value="0"><input type="checkbox" name="show_authorized_signature" value="1" @checked(old('show_authorized_signature', true))>Show authorized signature</label><p class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">Internal remarks are not shown in public links or customer PDFs.</p></article>
            </section>

            <div class="sticky bottom-4 z-10 flex flex-wrap justify-end gap-3 rounded-lg border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-slate-700 dark:bg-slate-900/95"><a href="{{ route('crm.proformas.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Cancel</a><button class="rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Create draft proforma</button></div>
        </form>
    </div>
@endsection

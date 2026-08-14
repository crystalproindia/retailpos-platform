@extends('layouts.admin')

@section('title', 'Create Proforma Invoice')
@section('page-title', 'Create Proforma Invoice')

@section('content')
    @php($taxMode = old('tax_mode', $quotation?->tax_mode ?? 'gst'))
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')
        <form method="POST" action="{{ route('crm.proformas.store') }}" class="space-y-6">
            @csrf
            <input type="hidden" name="lead_id" value="{{ $leadId }}">
            <input type="hidden" name="customer_id" value="{{ $customerId }}">
            <input type="hidden" name="quotation_id" value="{{ $quotation?->id }}">

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h1 class="text-2xl font-semibold text-slate-950 dark:text-white">Create Proforma Invoice</h1>
                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    @foreach(['title' => 'Proforma title', 'customer_company' => 'Customer company', 'customer_name' => 'Customer name', 'customer_email' => 'Customer email', 'customer_phone' => 'Customer phone', 'currency' => 'Currency', 'invoice_date' => 'Invoice date', 'due_date' => 'Due date'] as $field => $label)
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $label }}
                            <input name="{{ $field }}" type="{{ str_contains($field, 'date') ? 'date' : ($field === 'customer_email' ? 'email' : 'text') }}" value="{{ old($field, $quotation?->{$field} ?? ($field === 'title' ? 'RetailPOS Proforma Invoice' : ($field === 'currency' ? 'INR' : ($field === 'invoice_date' ? now()->toDateString() : '')))) }}" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2 dark:border-slate-700 dark:bg-slate-950">
                            @error($field)<span class="text-xs text-rose-600">{{ $message }}</span>@enderror
                        </label>
                    @endforeach
                </div>
                <label class="mt-4 block text-sm font-medium text-slate-700 dark:text-slate-200">Billing address<textarea name="billing_address" class="mt-2 block w-full rounded-lg border border-slate-300 p-3 dark:border-slate-700 dark:bg-slate-950">{{ old('billing_address', $quotation?->billing_address) }}</textarea></label>
                <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950"><h2 class="font-semibold text-slate-950 dark:text-white">Tax mode</h2><p class="mt-1 text-sm text-slate-500">No-GST proformas are allowed only for companies configured as unregistered or exempt. Totals are recalculated on save.</p><div class="mt-3 inline-flex rounded-lg border border-slate-300 bg-white p-1 dark:border-slate-700 dark:bg-slate-900"><label><input class="sr-only" type="radio" name="tax_mode" value="gst" @checked($taxMode === 'gst')><span class="block rounded-md px-4 py-2 text-sm font-semibold has-[:checked]:bg-slate-950 has-[:checked]:text-white">GST proforma</span></label><label><input class="sr-only" type="radio" name="tax_mode" value="no_gst" @checked($taxMode === 'no_gst')><span class="block rounded-md px-4 py-2 text-sm font-semibold has-[:checked]:bg-slate-950 has-[:checked]:text-white">No-GST proforma</span></label></div></div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="font-semibold text-slate-950 dark:text-white">Line items</h2>@foreach($items as $i => $item)<div class="mt-4 grid gap-3 md:grid-cols-5"><input name="items[{{ $i }}][name]" value="{{ $item['name'] }}" placeholder="Service" class="rounded-lg border p-2 dark:border-slate-700 dark:bg-slate-950"><input name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] }}" placeholder="Qty" class="rounded-lg border p-2 dark:border-slate-700 dark:bg-slate-950"><input name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] }}" placeholder="Unit price" class="rounded-lg border p-2 dark:border-slate-700 dark:bg-slate-950"><input name="items[{{ $i }}][discount_amount]" value="{{ $item['discount_amount'] ?? 0 }}" placeholder="Discount" class="rounded-lg border p-2 dark:border-slate-700 dark:bg-slate-950"><input name="items[{{ $i }}][tax_rate]" value="{{ $item['tax_rate'] ?? 0 }}" placeholder="Tax %" class="rounded-lg border p-2 dark:border-slate-700 dark:bg-slate-950"><input type="hidden" name="items[{{ $i }}][description]" value="{{ $item['description'] ?? '' }}"></div>@endforeach</section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Notes<textarea name="notes" class="mt-2 block w-full rounded-lg border p-3 dark:border-slate-700 dark:bg-slate-950">{{ old('notes') }}</textarea></label><label class="mt-4 block text-sm font-medium text-slate-700 dark:text-slate-200">Terms and conditions<textarea name="terms_conditions" class="mt-2 block w-full rounded-lg border p-3 dark:border-slate-700 dark:bg-slate-950">{{ old('terms_conditions') }}</textarea></label><label class="mt-4 block text-sm font-medium text-slate-700 dark:text-slate-200">Internal remarks<textarea name="internal_remarks" class="mt-2 block w-full rounded-lg border p-3 dark:border-slate-700 dark:bg-slate-950">{{ old('internal_remarks') }}</textarea></label><label class="mt-5 flex items-center gap-3 text-sm font-semibold text-slate-700 dark:text-slate-200"><input type="hidden" name="show_authorized_signature" value="0"><input type="checkbox" name="show_authorized_signature" value="1" @checked(old('show_authorized_signature', true))>Show authorized signature</label></section>
            <button class="rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Create proforma invoice</button>
        </form>
    </div>
@endsection

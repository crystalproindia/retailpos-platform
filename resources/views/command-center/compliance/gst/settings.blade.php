@extends('layouts.admin')

@section('title', 'GST Settings')
@section('page-title', 'GST Settings')

@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-amber-950">
        <h1 class="font-semibold">Accountant review required</h1>
        <p class="mt-1 text-sm">These settings support reviewable GST documents and exports. RetailPOS does not verify GSTIN authenticity, file returns, or submit e-invoices or e-way bills in this phase.</p>
    </section>
    <form method="POST" action="{{ route('compliance.gst.settings.update') }}" class="grid gap-5 rounded-lg border border-slate-200 bg-white p-6 shadow-sm md:grid-cols-2">
        @csrf @method('PUT')
        <label class="text-sm font-medium">Legal name<input name="legal_name" value="{{ old('legal_name', $settings->legal_name) }}" required class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-medium">Trade name<input name="trade_name" value="{{ old('trade_name', $settings->trade_name) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-medium">GSTIN<input name="gstin" value="{{ old('gstin', $settings->gstin) }}" maxlength="15" class="mt-1 w-full rounded-lg border-slate-300"><span class="mt-1 block text-xs font-normal text-slate-500">Format validation only. Authenticity is not verified.</span></label>
        <label class="text-sm font-medium">Registration type<select name="registration_type" class="mt-1 w-full rounded-lg border-slate-300">@foreach(['regular','composition','unregistered','exempt','SEZ','other'] as $type)<option value="{{ $type }}" @selected(old('registration_type', $settings->registration_type) === $type)>{{ ucfirst($type) }}</option>@endforeach</select></label>
        <label class="text-sm font-medium">State GST code<input name="state_code" value="{{ old('state_code', $settings->state_code) }}" maxlength="2" class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-medium">State name<input name="state_name" value="{{ old('state_name', $settings->state_name) }}" class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-medium">PAN<input name="pan" value="{{ old('pan', $settings->pan) }}" maxlength="10" class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-medium">Default place of supply<input name="default_place_of_supply_state_code" value="{{ old('default_place_of_supply_state_code', $settings->default_place_of_supply_state_code) }}" maxlength="2" class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-medium">Invoice series<input name="invoice_series" value="{{ old('invoice_series', $settings->invoice_series) }}" required class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-medium">Financial year<input name="financial_year" value="{{ old('financial_year', $settings->financial_year) }}" placeholder="2026-27" class="mt-1 w-full rounded-lg border-slate-300"></label>
        <label class="text-sm font-medium md:col-span-2">Registered address<textarea name="registered_address" rows="3" class="mt-1 w-full rounded-lg border-slate-300">{{ old('registered_address', $settings->registered_address) }}</textarea></label>
        <input type="hidden" name="tax_rounding_mode" value="half_up"><input type="hidden" name="export_type" value="domestic"><input type="hidden" name="reverse_charge_default" value="0"><input type="hidden" name="e_invoice_applicable" value="0"><input type="hidden" name="e_way_bill_applicable" value="0">
        <div class="md:col-span-2"><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Save GST settings</button></div>
    </form>
</div>
@endsection

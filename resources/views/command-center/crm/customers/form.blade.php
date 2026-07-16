@extends('layouts.admin')

@section('title', 'Convert Lead to Customer')
@section('page-title', 'Convert Lead to Customer')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><a href="{{ route('crm.leads.show', $lead) }}" class="hover:text-slate-950 dark:hover:text-white">Leads</a><span>/</span><span>Convert to Customer</span>
@endsection

@section('content')
    @php
        $action = $quotation
            ? route('crm.customers.store-for-quotation', $quotation)
            : route('crm.customers.store-for-lead', $lead);
    @endphp

    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-medium text-teal-700 dark:text-teal-300">Customer conversion</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Create a CRM customer from this lead</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">Review the information before conversion. The original lead remains linked for a complete sales history.</p>
            <dl class="mt-5 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-slate-500 dark:text-slate-400">Lead</dt><dd class="mt-1 font-semibold text-slate-950 dark:text-white">{{ $lead->title }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Company</dt><dd class="mt-1 font-semibold text-slate-950 dark:text-white">{{ $lead->business_name ?? 'Not recorded' }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Source</dt><dd class="mt-1 font-semibold text-slate-950 dark:text-white">{{ $lead->source?->name ?? 'Not recorded' }}</dd></div>
                <div><dt class="text-slate-500 dark:text-slate-400">Accepted quotation</dt><dd class="mt-1 font-semibold text-slate-950 dark:text-white">{{ $quotation?->quotation_number ?? 'None linked' }}</dd></div>
            </dl>
        </section>

        <form method="POST" action="{{ $action }}" class="space-y-6">
            @csrf
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:justify-between">
                    <div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Account profile</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">This is a CRM account, separate from retail/POS customer records.</p></div>
                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Customer code is assigned automatically</span>
                </div>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    @foreach ([
                        ['company_name', 'Company name', true],
                        ['display_name', 'Customer display name', true],
                        ['business_type', 'Business type', false],
                        ['tax_number', 'Tax number', false],
                    ] as [$field, $label, $required])
                        <label class="block"><span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $label }}@if ($required) <span class="text-rose-600">*</span>@endif</span><input name="{{ $field }}" value="{{ old($field, $customer->{$field}) }}" @if ($required) required @endif class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:border-teal-300 dark:focus:ring-teal-900" />@error($field)<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    @endforeach
                    <label class="block"><span class="text-sm font-medium text-slate-700 dark:text-slate-200">Status <span class="text-rose-600">*</span></span><select name="status" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">Select status</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(old('status', $customer->status?->value) === $status->value)>{{ $status->label() }}</option>@endforeach</select>@error('status')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    <label class="block"><span class="text-sm font-medium text-slate-700 dark:text-slate-200">Number of stores</span><input type="number" min="1" name="number_of_stores" value="{{ old('number_of_stores', $customer->number_of_stores) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white" />@error('number_of_stores')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Primary contact</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-2">
                    @foreach ([
                        ['contact_name', 'Contact name', true],
                        ['designation', 'Designation', false],
                        ['email', 'Email', false],
                        ['phone', 'Phone', false],
                    ] as [$field, $label, $required])
                        <label class="block"><span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $label }}@if ($required) <span class="text-rose-600">*</span>@endif</span><input type="{{ $field === 'email' ? 'email' : 'text' }}" name="{{ $field }}" value="{{ old($field, $field === 'contact_name' ? $customer->display_name : $customer->{$field}) }}" @if ($required) required @endif class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white" />@error($field)<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    @endforeach
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Address and conversion notes</h2>
                <div class="mt-5 grid gap-5 md:grid-cols-3">
                    @foreach (['country' => 'Country', 'state' => 'State', 'city' => 'City'] as $field => $label)
                        <label class="block"><span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $label }}</span><input name="{{ $field }}" value="{{ old($field, $customer->{$field}) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white" />@error($field)<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                    @endforeach
                </div>
                <label class="mt-5 block"><span class="text-sm font-medium text-slate-700 dark:text-slate-200">Billing address</span><textarea name="billing_address" rows="3" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('billing_address', $customer->billing_address) }}</textarea>@error('billing_address')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                <label class="mt-5 block"><span class="text-sm font-medium text-slate-700 dark:text-slate-200">Internal notes</span><textarea name="notes" rows="4" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('notes', $customer->notes) }}</textarea>@error('notes')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <a href="{{ $quotation ? route('crm.quotations.show', $quotation) : route('crm.leads.show', $lead) }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</a>
                <button class="inline-flex items-center justify-center rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Create CRM customer</button>
            </div>
        </form>
    </div>
@endsection

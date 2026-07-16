@extends('layouts.admin')

@section('title', 'CRM Customers')
@section('page-title', 'CRM Customers')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Customers</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div><p class="text-sm font-medium text-teal-700 dark:text-teal-300">Sales & CRM</p><h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">CRM Customers</h1><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">Accounts created from qualified leads and accepted quotations. Retail/POS customers continue to live in their own customer workspace.</p></div>
            </div>
            <form method="GET" class="mt-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_12rem_auto]">
                <label class="sr-only" for="customer-search">Search customers</label><input id="customer-search" name="search" value="{{ request('search') }}" placeholder="Search name, code, email, phone" class="block min-w-0 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white" />
                <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">All statuses</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
                <select name="business_type" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">All business types</option>@foreach ($businessTypes as $businessType)<option value="{{ $businessType }}" @selected(request('business_type') === $businessType)>{{ $businessType }}</option>@endforeach</select>
                <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Filter</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full min-w-[820px] text-left text-sm"><thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400"><tr><th class="px-5 py-3 font-semibold">Customer</th><th class="px-5 py-3 font-semibold">Primary contact</th><th class="px-5 py-3 font-semibold">Business type</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3 font-semibold">Converted</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@forelse ($customers as $customer)<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50"><td class="px-5 py-4"><a href="{{ route('crm.customers.show', $customer) }}" class="font-semibold text-slate-950 hover:text-teal-700 dark:text-white dark:hover:text-teal-300">{{ $customer->company_name }}</a><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $customer->customer_code }} · {{ $customer->display_name }}</p></td><td class="px-5 py-4 text-slate-700 dark:text-slate-200">{{ $customer->primaryContact?->name ?? 'Not recorded' }}<p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $customer->primaryContact?->email ?? $customer->primaryContact?->phone ?? '' }}</p></td><td class="px-5 py-4 text-slate-700 dark:text-slate-200">{{ $customer->business_type ?? 'Not recorded' }}</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ match($customer->status?->tone()) { 'success' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200', 'info' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200', 'danger' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200', default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200' } }}">{{ $customer->status?->label() }}</span></td><td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ $customer->converted_at?->format('d M Y') }}</td></tr>@empty<tr><td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No CRM customers match the current filters.</td></tr>@endforelse</tbody></table>
            </div>
            <div class="space-y-3 p-4 lg:hidden">@forelse ($customers as $customer)<a href="{{ route('crm.customers.show', $customer) }}" class="block rounded-lg border border-slate-200 p-4 dark:border-slate-800"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-950 dark:text-white">{{ $customer->company_name }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $customer->customer_code }} · {{ $customer->display_name }}</p></div><span class="text-xs font-semibold text-teal-700 dark:text-teal-300">{{ $customer->status?->label() }}</span></div><p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $customer->primaryContact?->name ?? 'No primary contact' }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $customer->business_type ?? 'Business type not recorded' }}</p></a>@empty<p class="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No CRM customers match the current filters.</p>@endforelse</div>
            @if ($customers->hasPages())<div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $customers->links() }}</div>@endif
        </section>
    </div>
@endsection

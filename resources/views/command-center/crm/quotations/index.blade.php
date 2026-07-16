@extends('layouts.admin')

@section('title', 'Quotations')
@section('page-title', 'Quotations')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Quotations</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Quotations</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Create customer-ready proposals from the lead conversation and track their decision state.</p>
                </div>
                <a href="{{ route('crm.leads.index') }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Choose a lead to create</a>
            </div>

            <form method="GET" class="mt-5 grid gap-3 md:grid-cols-[1fr_220px_auto]">
                <input name="search" value="{{ request('search') }}" placeholder="Search number, lead, company, or email" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Filter</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="divide-y divide-slate-200 md:hidden dark:divide-slate-800">
                @forelse ($quotations as $quotation)
                    <a href="{{ route('crm.quotations.show', $quotation) }}" class="block p-4 hover:bg-slate-50 dark:hover:bg-slate-800">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><p class="font-semibold text-slate-950 dark:text-white">{{ $quotation->quotation_number }}</p><p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">{{ $quotation->customer_company ?? $quotation->lead?->business_name ?? $quotation->lead?->title }}</p></div>
                            <x-status-badge :status="$quotation->status?->value" />
                        </div>
                        <div class="mt-3 flex items-center justify-between text-sm text-slate-500 dark:text-slate-400"><span>{{ $quotation->created_at?->format('d M Y') }}</span><strong class="text-slate-950 dark:text-white">{{ $quotation->currency }} {{ number_format((float) $quotation->grand_total, 2) }}</strong></div>
                    </a>
                @empty
                    <p class="p-10 text-center text-sm text-slate-500 dark:text-slate-400">No quotations match the selected filters.</p>
                @endforelse
            </div>
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400"><tr><th class="px-5 py-3">Quotation</th><th class="px-5 py-3">Lead / Customer</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Amount</th><th class="px-5 py-3">Created</th><th class="px-5 py-3">Valid until</th><th class="px-5 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($quotations as $quotation)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800"><td class="px-5 py-4"><a href="{{ route('crm.quotations.show', $quotation) }}" class="font-semibold text-slate-950 dark:text-white">{{ $quotation->quotation_number }}</a><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $quotation->title }}</p></td><td class="px-5 py-4"><p class="font-medium text-slate-800 dark:text-slate-100">{{ $quotation->customer_company ?? $quotation->lead?->business_name ?? 'Unlinked company' }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $quotation->customer_email ?? $quotation->lead?->email ?? $quotation->lead?->title }}</p></td><td class="px-5 py-4"><x-status-badge :status="$quotation->status?->value" /></td><td class="px-5 py-4 font-semibold text-slate-950 dark:text-white">{{ $quotation->currency }} {{ number_format((float) $quotation->grand_total, 2) }}</td><td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ $quotation->created_at?->format('d M Y') }}</td><td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ $quotation->valid_until?->format('d M Y') ?? 'No expiry' }}</td><td class="px-5 py-4 text-right"><a href="{{ route('crm.quotations.show', $quotation) }}" class="font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300">Open</a></td></tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No quotations match the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $quotations->links() }}</div>
        </section>
    </div>
@endsection

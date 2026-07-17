@extends('layouts.admin')

@section('title', $customer->company_name)
@section('page-title', $customer->company_name)

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span>
    <a href="{{ route('crm.customers.index') }}" class="hover:text-slate-950 dark:hover:text-white">Customers</a>
    <span>/</span><span>{{ $customer->customer_code }}</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                <div>
                    <p class="text-sm font-medium text-teal-700 dark:text-teal-300">{{ $customer->customer_code }}</p>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $customer->company_name }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ $customer->display_name }}
                        @if ($customer->business_type)
                            · {{ $customer->business_type }}
                        @endif
                    </p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-sky-100 px-3 py-1.5 text-sm font-semibold text-sky-800 dark:bg-sky-950 dark:text-sky-200">{{ $customer->status?->label() }}</span>
            </div>
        </section>
        <div class="flex flex-wrap justify-end gap-3"><a href="{{ route('crm.proformas.create-from-customer', $customer) }}" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Create Proforma Invoice</a>@if($customer->activeOnboarding)<a href="{{ route('crm.onboarding.show', $customer->activeOnboarding) }}" class="rounded-lg border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-700 dark:border-teal-800 dark:text-teal-300">Open Onboarding · {{ $customer->activeOnboarding->progress_percent }}%</a>@else<form method="POST" action="{{ route('crm.customers.onboarding.start', $customer) }}">@csrf<button class="rounded-lg border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-700 dark:border-teal-800 dark:text-teal-300">Start Onboarding</button></form>@endif</div>

        <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Account details</h2>
                <dl class="mt-5 space-y-3 text-sm">
                    @foreach (['Email' => $customer->email, 'Phone' => $customer->phone, 'Location' => collect([$customer->city, $customer->state, $customer->country])->filter()->join(', '), 'Tax Number' => $customer->tax_number, 'Stores' => $customer->number_of_stores, 'Source' => $customer->source, 'Converted' => $customer->converted_at?->format('d M Y, h:i A')] as $label => $value)
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800 dark:text-slate-100">{{ $value ?: 'Not recorded' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($customer->billing_address)
                    <div class="mt-5 border-t border-slate-100 pt-5 dark:border-slate-800">
                        <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Billing address</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $customer->billing_address }}</p>
                    </div>
                @endif
                @if ($customer->notes)
                    <div class="mt-5 border-t border-slate-100 pt-5 dark:border-slate-800">
                        <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Internal notes</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $customer->notes }}</p>
                    </div>
                @endif
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Primary contact</h2>
                <div class="mt-5 space-y-3">
                    @forelse ($customer->contacts as $contact)
                        <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                            <div class="flex items-start justify-between gap-3">
                                <div><p class="font-semibold text-slate-950 dark:text-white">{{ $contact->name }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $contact->designation ?? 'Primary contact' }}</p></div>
                                @if ($contact->is_primary)<span class="rounded-full bg-teal-100 px-2.5 py-1 text-xs font-semibold text-teal-800 dark:bg-teal-950 dark:text-teal-200">Primary</span>@endif
                            </div>
                            <p class="mt-3 text-sm text-slate-700 dark:text-slate-200">{{ $contact->email ?? 'No email recorded' }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $contact->phone ?? 'No phone recorded' }}</p>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No contacts have been recorded.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([['title' => 'Stores', 'copy' => 'Multi-store management will appear here.'], ['title' => 'Subscriptions', 'copy' => 'Customer subscriptions will appear here.'], ['title' => 'Invoices', 'copy' => 'CRM invoice history will appear here.'], ['title' => 'Support Tickets', 'copy' => 'Customer support history will appear here.']] as $area)
                <article class="rounded-lg border border-dashed border-slate-300 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"><h2 class="text-base font-semibold text-slate-950 dark:text-white">{{ $area['title'] }}</h2><p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $area['copy'] }}</p><p class="mt-4 text-xs font-semibold uppercase text-slate-400 dark:text-slate-500">Coming soon</p></article>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Linked lead</h2>
                @if ($customer->lead)
                    <a href="{{ route('crm.leads.show', $customer->lead) }}" class="mt-5 block rounded-lg border border-slate-200 p-4 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"><p class="font-semibold text-slate-950 dark:text-white">{{ $customer->lead->title }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $customer->lead->status?->name ?? 'No status' }}</p></a>
                @else
                    <p class="mt-5 rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No lead is linked to this customer.</p>
                @endif
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Accepted quotation</h2>
                @if ($customer->quotation)
                    <a href="{{ route('crm.quotations.show', $customer->quotation) }}" class="mt-5 block rounded-lg border border-slate-200 p-4 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"><p class="font-semibold text-slate-950 dark:text-white">{{ $customer->quotation->quotation_number }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $customer->quotation->currency }} {{ number_format((float) $customer->quotation->grand_total, 2) }}</p></a>
                @else
                    <p class="mt-5 rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No accepted quotation was linked during conversion.</p>
                @endif
            </article>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Conversion activity</h2>
            <div class="mt-5 space-y-3">
                @forelse ($customer->auditLogs as $audit)
                    <div class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-800"><p class="text-sm font-medium text-slate-950 dark:text-white">{{ $audit->description }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $audit->created_at?->format('d M Y, h:i A') }} by {{ $audit->user?->name ?? 'System' }}</p></div>
                @empty
                    <p class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No conversion activity has been recorded yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection

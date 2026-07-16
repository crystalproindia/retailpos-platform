@extends('layouts.admin')

@section('title', 'CRM')
@section('page-title', 'CRM')

@section('breadcrumbs')
    <span>/</span>
    <span>CRM</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <p class="text-sm font-medium text-teal-700 dark:text-teal-300">Sales & CRM</p>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">CRM Command Center</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">Monitor lead health, pipeline value, sources, statuses, and upcoming work from company-scoped CRM data.</p>
                </div>
                <a href="{{ route('crm.leads.create') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">New lead</a>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                ['label' => 'Draft Quotations', 'value' => $quotationMetrics['draft'], 'tone' => 'bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-100'],
                ['label' => 'Sent Quotations', 'value' => $quotationMetrics['sent'], 'tone' => 'bg-sky-50 text-sky-950 dark:bg-sky-950/30 dark:text-sky-100'],
                ['label' => 'Accepted Quotations', 'value' => $quotationMetrics['accepted'], 'tone' => 'bg-teal-50 text-teal-950 dark:bg-teal-950/30 dark:text-teal-100'],
                ['label' => 'Total Quotation Value', 'value' => 'INR '.number_format($quotationMetrics['total_value'], 0), 'tone' => 'bg-violet-50 text-violet-950 dark:bg-violet-950/30 dark:text-violet-100'],
                ['label' => 'Pending Quotation Value', 'value' => 'INR '.number_format($quotationMetrics['pending_value'], 0), 'tone' => 'bg-amber-50 text-amber-950 dark:bg-amber-950/30 dark:text-amber-100'],
            ] as $card)
                <article class="rounded-lg border border-slate-200 p-5 shadow-sm dark:border-slate-800 {{ $card['tone'] }}"><p class="text-sm font-medium opacity-70">{{ $card['label'] }}</p><p class="mt-3 text-2xl font-semibold">{{ $card['value'] }}</p></article>
            @endforeach
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Total Leads', 'value' => $metrics['total_leads']],
                ['label' => 'New Leads', 'value' => $metrics['new_leads']],
                ['label' => 'Qualified Leads', 'value' => $metrics['qualified_leads']],
                ['label' => 'Demo Scheduled', 'value' => $metrics['demo_scheduled']],
                ['label' => 'Won Leads', 'value' => $metrics['won_leads']],
                ['label' => 'Lost Leads', 'value' => $metrics['lost_leads']],
                ['label' => 'Pipeline Value', 'value' => '₹'.number_format((float) $metrics['pipeline_value'], 0)],
                ['label' => 'Overdue Follow-ups', 'value' => $metrics['overdue_followups']],
            ] as $card)
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-white">{{ $card['value'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['label' => 'Scheduled Demos', 'value' => $demoMetrics['scheduled_demos'], 'tone' => 'bg-violet-50 text-violet-950 dark:bg-violet-950/30 dark:text-violet-100'],
                ['label' => 'Demos Today', 'value' => $demoMetrics['demos_today'], 'tone' => 'bg-sky-50 text-sky-950 dark:bg-sky-950/30 dark:text-sky-100'],
                ['label' => 'Upcoming Demos', 'value' => $demoMetrics['upcoming_demos'], 'tone' => 'bg-teal-50 text-teal-950 dark:bg-teal-950/30 dark:text-teal-100'],
                ['label' => 'Completed Demos', 'value' => $demoMetrics['completed_demos'], 'tone' => 'bg-emerald-50 text-emerald-950 dark:bg-emerald-950/30 dark:text-emerald-100'],
                ['label' => 'Cancelled Demos', 'value' => $demoMetrics['cancelled_demos'], 'tone' => 'bg-rose-50 text-rose-950 dark:bg-rose-950/30 dark:text-rose-100'],
                ['label' => 'Overdue Demo Follow-ups', 'value' => $demoMetrics['overdue_demos'], 'tone' => 'bg-amber-50 text-amber-950 dark:bg-amber-950/30 dark:text-amber-100'],
            ] as $card)
                <article class="rounded-lg border border-slate-200 p-5 shadow-sm dark:border-slate-800 {{ $card['tone'] }}">
                    <p class="text-sm font-medium opacity-70">{{ $card['label'] }}</p>
                    <p class="mt-3 text-3xl font-semibold">{{ $card['value'] }}</p>
                </article>
            @endforeach
        </section>

        @include('command-center.crm.partials.upcoming-demos', [
            'heading' => 'Upcoming Demo Schedule',
            'emptyHeading' => 'No upcoming demos',
            'emptyMessage' => 'Schedule a demo from a lead to keep the next customer conversations visible here.',
        ])

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Total Customers', 'value' => $customerMetrics['total'], 'tone' => 'bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-100'],
                ['label' => 'New This Month', 'value' => $customerMetrics['new_this_month'], 'tone' => 'bg-sky-50 text-sky-950 dark:bg-sky-950/30 dark:text-sky-100'],
                ['label' => 'Customers Onboarding', 'value' => $customerMetrics['onboarding'], 'tone' => 'bg-amber-50 text-amber-950 dark:bg-amber-950/30 dark:text-amber-100'],
                ['label' => 'Active Customers', 'value' => $customerMetrics['active'], 'tone' => 'bg-teal-50 text-teal-950 dark:bg-teal-950/30 dark:text-teal-100'],
            ] as $card)
                <article class="rounded-lg border border-slate-200 p-5 shadow-sm dark:border-slate-800 {{ $card['tone'] }}">
                    <p class="text-sm font-medium opacity-70">{{ $card['label'] }}</p>
                    <p class="mt-3 text-3xl font-semibold">{{ $card['value'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-4">
                <div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Latest CRM Customers</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Recently converted accounts from the CRM pipeline.</p></div>
                <a href="{{ route('crm.customers.index') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">View all</a>
            </div>
            <div class="mt-5 grid gap-3 lg:grid-cols-2">
                @forelse ($customerMetrics['latest'] as $customer)
                    <a href="{{ route('crm.customers.show', $customer) }}" class="rounded-lg border border-slate-200 p-4 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-950 dark:text-white">{{ $customer->company_name }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $customer->customer_code }} · {{ $customer->primaryContact?->name ?? $customer->display_name }}</p></div><span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $customer->status?->label() }}</span></div>
                    </a>
                @empty
                    <p class="lg:col-span-2 rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No CRM customers have been created yet.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="flex items-center justify-between"><div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Latest Quotations</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Recent proposals across the CRM pipeline.</p></div><a href="{{ route('crm.quotations.index') }}" class="text-sm font-semibold text-slate-700 dark:text-slate-300">View all</a></div><div class="mt-5 grid gap-3 lg:grid-cols-2">@forelse($quotationMetrics['latest'] as $quotation)<a href="{{ route('crm.quotations.show', $quotation) }}" class="rounded-lg border border-slate-200 p-4 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"><div class="flex justify-between gap-3"><div><p class="font-semibold text-slate-950 dark:text-white">{{ $quotation->quotation_number }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $quotation->lead?->business_name ?? $quotation->lead?->title }}</p></div><span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $quotation->status?->label() }}</span></div><p class="mt-3 text-sm font-semibold text-slate-950 dark:text-white">{{ $quotation->currency }} {{ number_format((float) $quotation->grand_total, 2) }}</p></a>@empty<p class="lg:col-span-2 rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No quotations have been created yet.</p>@endforelse</div></section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Leads by Status</h2>
                <div class="mt-5 space-y-3">
                    @foreach ($metrics['leads_by_status'] as $item)
                        <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-800">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $item['name'] }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $item['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Leads by Source</h2>
                <div class="mt-5 space-y-3">
                    @foreach ($metrics['leads_by_source'] as $item)
                        <div class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-800">
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $item['name'] }}</span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $item['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Recent Leads</h2>
                    <a href="{{ route('crm.leads.index') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">View all</a>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($metrics['recent_leads'] as $lead)
                        <a href="{{ route('crm.leads.show', $lead) }}" class="block rounded-lg border border-slate-200 px-4 py-3 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800">
                            <p class="font-medium text-slate-950 dark:text-white">{{ $lead->title }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $lead->business_name ?? $lead->contact_name ?? 'Unlinked lead' }}</p>
                        </a>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No leads yet.</p>
                    @endforelse
                </div>
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Upcoming Activities</h2>
                    <a href="{{ route('crm.activities.index') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Manage</a>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($metrics['upcoming_activities'] as $activity)
                        <div class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-800">
                            <p class="font-medium text-slate-950 dark:text-white">{{ $activity->subject }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $activity->scheduled_at?->format('d M Y, h:i A') ?? 'Not scheduled' }}</p>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No upcoming activities.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
@endsection

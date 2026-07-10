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

@extends('layouts.admin')

@section('title', 'Sales Pipeline')
@section('page-title', 'Sales Pipeline')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Sales Pipeline</span>
@endsection

@section('content')
    @php
        $stageStyles = [
            'new_lead' => ['column' => 'border-slate-200 bg-slate-50/70 dark:border-slate-800 dark:bg-slate-950/40', 'marker' => 'bg-slate-500', 'badge' => 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200', 'card' => 'border-slate-200 dark:border-slate-800'],
            'contacted' => ['column' => 'border-sky-200 bg-sky-50/50 dark:border-sky-950 dark:bg-sky-950/20', 'marker' => 'bg-sky-500', 'badge' => 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200', 'card' => 'border-sky-100 dark:border-sky-950'],
            'demo_scheduled' => ['column' => 'border-indigo-200 bg-indigo-50/50 dark:border-indigo-950 dark:bg-indigo-950/20', 'marker' => 'bg-indigo-500', 'badge' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200', 'card' => 'border-indigo-100 dark:border-indigo-950'],
            'proposal_sent' => ['column' => 'border-amber-200 bg-amber-50/50 dark:border-amber-950 dark:bg-amber-950/20', 'marker' => 'bg-amber-500', 'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200', 'card' => 'border-amber-100 dark:border-amber-950'],
            'proforma_sent' => ['column' => 'border-teal-200 bg-teal-50/50 dark:border-teal-950 dark:bg-teal-950/20', 'marker' => 'bg-teal-500', 'badge' => 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-200', 'card' => 'border-teal-100 dark:border-teal-950'],
            'partially_paid' => ['column' => 'border-cyan-200 bg-cyan-50/50 dark:border-cyan-950 dark:bg-cyan-950/20', 'marker' => 'bg-cyan-500', 'badge' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-200', 'card' => 'border-cyan-100 dark:border-cyan-950'],
            'won' => ['column' => 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-950 dark:bg-emerald-950/20', 'marker' => 'bg-emerald-500', 'badge' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200', 'card' => 'border-emerald-200 dark:border-emerald-950'],
            'lost' => ['column' => 'border-rose-200 bg-rose-50/60 dark:border-rose-950 dark:bg-rose-950/20', 'marker' => 'bg-rose-500', 'badge' => 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200', 'card' => 'border-rose-200 dark:border-rose-950'],
        ];
        $query = request()->query();
    @endphp

    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="flex flex-col gap-5 border-b border-slate-200 pb-6 dark:border-slate-800 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-semibold text-teal-700 dark:text-teal-300">Sales workspace</p>
                <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Pipeline</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">Move opportunities through a clear, shared view of every customer conversation and commercial milestone.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <div class="inline-flex rounded-lg border border-slate-200 bg-white p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Pipeline view">
                    <a href="{{ route('crm.pipeline.index', array_merge($query, ['view' => 'board'])) }}" class="rounded-md px-3 py-2 text-sm font-semibold {{ $viewMode === 'board' ? 'bg-slate-950 text-white dark:bg-teal-300 dark:text-slate-950' : 'text-slate-500 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white' }}">Board</a>
                    <a href="{{ route('crm.pipeline.index', array_merge($query, ['view' => 'list'])) }}" class="rounded-md px-3 py-2 text-sm font-semibold {{ $viewMode === 'list' ? 'bg-slate-950 text-white dark:bg-teal-300 dark:text-slate-950' : 'text-slate-500 hover:text-slate-950 dark:text-slate-400 dark:hover:text-white' }}">List</a>
                </div>
                @can('crm.leads.create')
                    <a href="{{ route('crm.leads.create') }}" class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-teal-800 dark:bg-teal-300 dark:text-slate-950">New lead</a>
                @endcan
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="border-l-4 border-slate-500 bg-white px-5 py-4 shadow-sm dark:bg-slate-900"><p class="text-sm font-medium text-slate-500 dark:text-slate-400">Active deals</p><p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ number_format($metrics['active_deals']) }}</p></article>
            <article class="border-l-4 border-teal-500 bg-white px-5 py-4 shadow-sm dark:bg-slate-900"><p class="text-sm font-medium text-slate-500 dark:text-slate-400">Current pipeline value</p><p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">INR {{ number_format((float) $metrics['pipeline_value'], 0) }}</p></article>
            <article class="border-l-4 border-emerald-500 bg-white px-5 py-4 shadow-sm dark:bg-slate-900"><p class="text-sm font-medium text-slate-500 dark:text-slate-400">Won value</p><p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">INR {{ number_format((float) $metrics['won_value'], 0) }}</p></article>
            <article class="border-l-4 border-rose-500 bg-white px-5 py-4 shadow-sm dark:bg-slate-900"><p class="text-sm font-medium text-slate-500 dark:text-slate-400">Overdue follow-ups</p><p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ number_format($metrics['overdue_follow_ups']) }}</p></article>
        </section>

        <section class="border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <form method="GET" action="{{ route('crm.pipeline.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <input type="hidden" name="view" value="{{ $viewMode }}">
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search deal, company, email or phone" class="xl:col-span-2">
                <select name="stage"><option value="">All stages</option>@foreach ($stages as $stage)<option value="{{ $stage->value }}" @selected(($filters['stage'] ?? null) === $stage->value)>{{ $stage->label() }}</option>@endforeach</select>
                <select name="assigned_user_id"><option value="">All owners</option>@foreach ($owners as $owner)<option value="{{ $owner->id }}" @selected((string) ($filters['assigned_user_id'] ?? '') === (string) $owner->id)>{{ $owner->name }}</option>@endforeach</select>
                <select name="source_id"><option value="">All sources</option>@foreach ($sources as $source)<option value="{{ $source->id }}" @selected((string) ($filters['source_id'] ?? '') === (string) $source->id)>{{ $source->name }}</option>@endforeach</select>
                <select name="follow_up"><option value="">All follow-ups</option><option value="overdue" @selected(($filters['follow_up'] ?? null) === 'overdue')>Overdue</option><option value="today" @selected(($filters['follow_up'] ?? null) === 'today')>Due today</option><option value="upcoming" @selected(($filters['follow_up'] ?? null) === 'upcoming')>Upcoming</option><option value="none" @selected(($filters['follow_up'] ?? null) === 'none')>No follow-up</option></select>
                <select name="payment_status"><option value="">Any payment status</option><option value="pending" @selected(($filters['payment_status'] ?? null) === 'pending')>Payment pending</option><option value="partial" @selected(($filters['payment_status'] ?? null) === 'partial')>Partially paid</option><option value="paid" @selected(($filters['payment_status'] ?? null) === 'paid')>Paid</option></select>
                <select name="ai_category"><option value="">Any AI category</option><option value="hot" @selected(($filters['ai_category'] ?? null) === 'hot')>Hot</option><option value="warm" @selected(($filters['ai_category'] ?? null) === 'warm')>Warm</option><option value="at_risk" @selected(($filters['ai_category'] ?? null) === 'at_risk')>At Risk</option><option value="cold" @selected(($filters['ai_category'] ?? null) === 'cold')>Cold</option></select>
                <select name="ai_priority"><option value="">Any AI priority</option><option value="urgent" @selected(($filters['ai_priority'] ?? null) === 'urgent')>Urgent</option><option value="high" @selected(($filters['ai_priority'] ?? null) === 'high')>High</option><option value="medium" @selected(($filters['ai_priority'] ?? null) === 'medium')>Medium</option><option value="low" @selected(($filters['ai_priority'] ?? null) === 'low')>Low</option></select>
                <input type="date" name="created_from" value="{{ $filters['created_from'] ?? '' }}" aria-label="Created from">
                <input type="date" name="created_to" value="{{ $filters['created_to'] ?? '' }}" aria-label="Created to">
                <input type="date" name="activity_from" value="{{ $filters['activity_from'] ?? '' }}" aria-label="Last activity from" title="Last activity from">
                <input type="date" name="activity_to" value="{{ $filters['activity_to'] ?? '' }}" aria-label="Last activity to" title="Last activity to">
                <input type="number" min="0" name="min_value" value="{{ $filters['min_value'] ?? '' }}" placeholder="Minimum value">
                <input type="number" min="0" name="max_value" value="{{ $filters['max_value'] ?? '' }}" placeholder="Maximum value">
                <div class="flex gap-2 xl:col-span-2"><button class="flex-1 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950">Apply filters</button><a href="{{ route('crm.pipeline.index', ['view' => $viewMode]) }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Reset</a></div>
            </form>
        </section>

        @if ($viewMode === 'board')
            <section data-pipeline-board data-move-url="{{ route('crm.pipeline.cards.move', ['lead' => '__lead__']) }}" data-csrf="{{ csrf_token() }}" class="relative">
                <div data-pipeline-feedback class="pointer-events-none fixed inset-x-4 bottom-5 z-50 hidden rounded-lg border border-emerald-200 bg-white px-4 py-3 text-sm font-semibold text-emerald-800 shadow-lg sm:left-auto sm:right-5 sm:max-w-sm dark:border-emerald-950 dark:bg-slate-900 dark:text-emerald-200" role="status"></div>
                <div class="overflow-x-auto pb-3">
                    <div class="grid min-w-max grid-flow-col auto-cols-[minmax(17.5rem,20rem)] gap-4">
                        @foreach ($columns as $column)
                            @php($style = $stageStyles[$column['stage']->value])
                            <article data-pipeline-dropzone data-stage="{{ $column['stage']->value }}" class="pipeline-dropzone min-h-[31rem] rounded-lg border p-3 {{ $style['column'] }}">
                                <header class="flex items-start justify-between gap-3 border-b border-slate-200/70 pb-3 dark:border-slate-800">
                                    <div class="min-w-0"><div class="flex items-center gap-2"><span class="size-2 rounded-full {{ $style['marker'] }}"></span><h2 class="truncate font-semibold text-slate-950 dark:text-white">{{ $column['stage']->label() }}</h2></div><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $column['stage']->probability() }}% likelihood</p></div>
                                    <div class="text-right"><span data-pipeline-count class="rounded-full px-2 py-1 text-xs font-semibold {{ $style['badge'] }}">{{ $column['count'] }}</span><p class="mt-2 text-xs font-semibold text-slate-600 dark:text-slate-300">INR {{ number_format((float) $column['value'], 0) }}</p></div>
                                </header>
                                <div data-pipeline-cards class="mt-3 space-y-3">
                                    @forelse ($column['cards'] as $card)
                                        @include('command-center.crm.pipeline.partials.card', ['card' => $card])
                                    @empty
                                        <p data-pipeline-empty class="rounded-lg border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">Drop a deal here or use Move stage.</p>
                                    @endforelse
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>
        @else
            <section class="overflow-hidden border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="overflow-x-auto">
                    <table class="min-w-[980px] w-full text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-400"><tr><th class="px-5 py-3">Deal</th><th class="px-5 py-3">Stage</th><th class="px-5 py-3">AI score</th><th class="px-5 py-3">Owner</th><th class="px-5 py-3">Value</th><th class="px-5 py-3">Follow-up</th><th class="px-5 py-3">Activity</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($cards as $card)
                                @php($lead = $card['lead'])
                                @php($style = $stageStyles[$card['stage']->value])
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50"><td class="px-5 py-4"><a href="{{ route('crm.leads.show', $lead) }}" class="font-semibold text-slate-950 hover:text-teal-700 dark:text-white dark:hover:text-teal-300">{{ $lead->title }}</a><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $lead->business_name ?? $lead->contact_name ?? 'Unassigned account' }}</p></td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $style['badge'] }}">{{ $card['stage']->label() }}</span></td><td class="px-5 py-4">@if($card['ai_score'])<span class="font-semibold text-slate-950 dark:text-white">{{ $card['ai_score']->score }}</span><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $card['ai_score']->category->label() }}</p>@else<span class="text-slate-400">Not analyzed</span>@endif</td><td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $lead->assignedUser?->name ?? 'Unassigned' }}</td><td class="px-5 py-4 font-semibold text-slate-950 dark:text-white">{{ $card['currency'] }} {{ number_format((float) $card['value'], 0) }}</td><td class="px-5 py-4 {{ $card['is_overdue'] ? 'font-semibold text-rose-700 dark:text-rose-300' : 'text-slate-600 dark:text-slate-300' }}">{{ $lead->next_follow_up_at?->format('d M, h:i A') ?? 'Not set' }}</td><td class="max-w-[14rem] truncate px-5 py-4 text-slate-500 dark:text-slate-400">{{ $card['latest_activity']?->subject ?? 'No activity yet' }}</td><td class="px-5 py-4 text-right"><a href="{{ route('crm.leads.show', $lead) }}" class="font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300">Open</a></td></tr>
                            @empty
                                <tr><td colspan="8" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">No deals match the current pipeline filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
@endsection

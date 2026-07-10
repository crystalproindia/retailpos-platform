@extends('layouts.admin')

@section('title', 'CRM Follow-ups')
@section('page-title', 'CRM Follow-ups')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Follow-ups</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Follow-up Queue</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Upcoming and overdue CRM tasks, calls, meetings, and follow-ups.</p>
                </div>
                <a href="{{ route('crm.followups.index', ['overdue' => request()->boolean('overdue') ? 0 : 1]) }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">{{ request()->boolean('overdue') ? 'Show all' : 'Overdue only' }}</a>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($activities as $activity)
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-medium text-teal-700 dark:text-teal-300">{{ $activity->type?->label() }}</p>
                    <h2 class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $activity->subject }}</h2>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $activity->scheduled_at?->format('d M Y, h:i A') ?? 'Not scheduled' }}</p>
                    @if ($activity->lead)
                        <a href="{{ route('crm.leads.show', $activity->lead) }}" class="mt-4 inline-flex text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">{{ $activity->lead->title }}</a>
                    @endif
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 md:col-span-2 xl:col-span-3">No follow-ups found.</div>
            @endforelse
        </section>
    </div>
@endsection

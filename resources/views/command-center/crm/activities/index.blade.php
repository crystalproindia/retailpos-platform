@extends('layouts.admin')

@section('title', 'CRM Activities')
@section('page-title', 'CRM Activities')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Activities</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <form method="POST" action="{{ route('crm.activities.store') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                @csrf
                <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Schedule Activity</h1>
                <div class="mt-5 space-y-4">
                    <input name="subject" required placeholder="Subject" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <div class="grid gap-3 md:grid-cols-2">
                        <select name="type" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        <select name="priority" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <select name="crm_lead_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="">No lead</option>
                        @foreach ($leads as $lead)
                            <option value="{{ $lead->id }}">{{ $lead->title }}</option>
                        @endforeach
                    </select>
                    <input type="datetime-local" name="scheduled_at" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <textarea name="description" rows="4" placeholder="Description" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                    <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Schedule</button>
                </div>
            </form>

            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-3 md:flex-row md:items-center">
                    <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Activity Queue</h2>
                    <form method="GET" action="{{ route('crm.activities.index') }}" class="flex gap-2">
                        <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <option value="">Open</option>
                            <option value="completed" @selected(request('status') === 'completed')>Completed</option>
                            <option value="overdue" @selected(request('status') === 'overdue')>Overdue</option>
                        </select>
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Filter</button>
                    </form>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse ($activities as $activity)
                        <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                            <div class="flex flex-col justify-between gap-3 md:flex-row">
                                <div>
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $activity->subject }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $activity->type?->label() }} · {{ $activity->scheduled_at?->format('d M Y, h:i A') ?? 'Not scheduled' }}</p>
                                </div>
                                @if (! $activity->completed_at)
                                    <form method="POST" action="{{ route('crm.activities.complete', $activity) }}">
                                        @csrf
                                        <button class="rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Complete</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No activities found.</p>
                    @endforelse
                </div>
                <div class="mt-5">{{ $activities->links() }}</div>
            </article>
        </section>
    </div>
@endsection

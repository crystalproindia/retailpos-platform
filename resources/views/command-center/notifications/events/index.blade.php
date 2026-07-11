@extends('layouts.admin')

@section('title', 'Domain Event Log')
@section('page-title', 'Domain Event Log')

@section('breadcrumbs')
    <span>/</span><span>Notifications</span><span>/</span><span>Event Log</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.notifications.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Event Log</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Company-scoped domain events with correlation IDs for audit, notifications, webhooks, and future automation.</p>

            <form method="GET" action="{{ route('notifications.events.index') }}" class="mt-5 grid gap-3 lg:grid-cols-[1fr_160px_180px_180px_auto]">
                <select name="event_key" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All events</option>
                    @foreach ($eventOptions as $key => $definition)
                        <option value="{{ $key }}" @selected(request('event_key') === $key)>{{ $definition['name'] }}</option>
                    @endforeach
                </select>
                <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All statuses</option>
                    @foreach (['recorded', 'processed', 'failed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                    @endforeach
                </select>
                <select name="actor" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All actors</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) request('actor') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
                <input name="aggregate_type" value="{{ request('aggregate_type') }}" placeholder="Aggregate type" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Filter</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Event</th>
                            <th class="px-5 py-3">Aggregate</th>
                            <th class="px-5 py-3">Actor</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Correlation</th>
                            <th class="px-5 py-3">Occurred</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($events as $event)
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $eventOptions[$event->event_key]['name'] ?? str($event->event_key)->headline() }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $event->event_key }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">
                                    <p>{{ class_basename($event->aggregate_type) ?: 'System' }}</p>
                                    <p class="mt-1 text-xs text-slate-400">#{{ $event->aggregate_id ?? 'n/a' }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $event->user?->name ?? 'System' }}</td>
                                <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ str($event->status)->headline() }}</span></td>
                                <td class="max-w-72 truncate px-5 py-4 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $event->correlation_id }}</td>
                                <td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ $event->occurred_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">No domain events found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $events->links() }}</div>
        </section>
    </div>
@endsection

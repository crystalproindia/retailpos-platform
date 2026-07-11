@extends('layouts.admin')

@section('title', 'Operations Monitor')
@section('page-title', 'Operations Monitor')

@section('breadcrumbs')
    <span>/</span><span>Operations</span>
@endsection

@section('content')
    @php
        $statusClass = fn (string $status) => match ($status) {
            'healthy', 'success' => 'bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-200',
            'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-200',
            'critical', 'failed' => 'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-200',
            default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        };
    @endphp

    <div class="space-y-6">
        @include('command-center.operations.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Production operations</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Live health, queue, scheduler, and delivery signals for the Command Center.</p>
                </div>
                <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass($overallStatus) }}">{{ str($overallStatus)->headline() }}</span>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['label' => 'Pending jobs', 'value' => $queueSummary['pending_count'], 'tone' => $queueSummary['pending_count'] ? 'warning' : 'healthy'],
                ['label' => 'Failed jobs', 'value' => $failedJobsCount, 'tone' => $failedJobsCount ? 'critical' : 'healthy'],
                ['label' => 'Notification failures', 'value' => $notificationFailures, 'tone' => $notificationFailures ? 'warning' : 'healthy'],
                ['label' => 'Webhook failures', 'value' => $webhookFailures, 'tone' => $webhookFailures ? 'warning' : 'healthy'],
                ['label' => 'Event failures', 'value' => $eventFailures, 'tone' => $eventFailures ? 'critical' : 'healthy'],
                ['label' => 'Database', 'value' => $latestChecks->get('database')?->status ?? 'unknown', 'tone' => $latestChecks->get('database')?->status ?? 'unknown'],
                ['label' => 'Cache', 'value' => $latestChecks->get('cache')?->status ?? 'unknown', 'tone' => $latestChecks->get('cache')?->status ?? 'unknown'],
                ['label' => 'Storage', 'value' => $latestChecks->get('storage')?->status ?? 'unknown', 'tone' => $latestChecks->get('storage')?->status ?? 'unknown'],
            ] as $card)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                    <div class="mt-3 flex items-end justify-between gap-3">
                        <p class="text-2xl font-semibold text-slate-950 dark:text-white">{{ is_numeric($card['value']) ? number_format($card['value']) : str($card['value'])->headline() }}</p>
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClass($card['tone']) }}">{{ str($card['tone'])->headline() }}</span>
                    </div>
                </div>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-semibold text-slate-950 dark:text-white">Latest health checks</h2>
                    <a href="{{ route('operations.health.index') }}" class="text-sm font-semibold text-teal-700 dark:text-teal-300">Open health</a>
                </div>
                <div class="mt-4 space-y-3">
                    @forelse ($latestChecks->take(8) as $check)
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 p-3 dark:border-slate-800">
                            <div>
                                <p class="text-sm font-medium text-slate-950 dark:text-white">{{ $check->name }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $check->message }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusClass($check->status) }}">{{ str($check->status)->headline() }}</span>
                        </div>
                    @empty
                        <p class="rounded-lg border border-slate-100 p-4 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">No health checks have been captured yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="font-semibold text-slate-950 dark:text-white">Runtime</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    @foreach (['environment', 'laravel_version', 'php_version', 'queue_driver', 'cache_driver', 'git_commit'] as $key)
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $appInfo[$key]['label'] }}</dt>
                            <dd class="max-w-48 truncate font-medium text-slate-950 dark:text-white">{{ $appInfo[$key]['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-600 dark:bg-slate-950 dark:text-slate-300">
                    Last scheduled run:
                    <span class="font-semibold text-slate-950 dark:text-white">{{ $lastScheduledRun?->command ?? 'Not tracked yet' }}</span>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <a href="{{ route('notifications.deliveries.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-teal-300 dark:border-slate-800 dark:bg-slate-900">
                <p class="font-semibold text-slate-950 dark:text-white">Notification deliveries</p>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Inspect delivery failures, pending sends, and retry state.</p>
            </a>
            <a href="{{ route('notifications.webhooks.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-teal-300 dark:border-slate-800 dark:bg-slate-900">
                <p class="font-semibold text-slate-950 dark:text-white">Webhook deliveries</p>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Review disabled endpoints and repeated failures.</p>
            </a>
            <a href="{{ route('notifications.events.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm transition hover:border-teal-300 dark:border-slate-800 dark:bg-slate-900">
                <p class="font-semibold text-slate-950 dark:text-white">Event logs</p>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Trace domain event processing and failure status.</p>
            </a>
        </section>
    </div>
@endsection

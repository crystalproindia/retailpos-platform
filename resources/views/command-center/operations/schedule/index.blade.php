@extends('layouts.admin')

@section('title', 'Schedule Monitor')
@section('page-title', 'Schedule Monitor')

@section('breadcrumbs')
    <span>/</span><span>Operations</span><span>/</span><span>Schedule</span>
@endsection

@section('content')
    @php
        $statusClass = fn (?string $status) => match ($status) {
            'success' => 'bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-200',
            'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-200',
            'failed' => 'bg-rose-100 text-rose-700 dark:bg-rose-900 dark:text-rose-200',
            default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        };
    @endphp

    <div class="space-y-6">
        @include('command-center.operations.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Schedule Monitor</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Configured scheduled commands, expected cadence, next run estimates, and tracked run history.</p>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                        <tr><th class="px-5 py-3">Command</th><th class="px-5 py-3">Frequency</th><th class="px-5 py-3">Next run</th><th class="px-5 py-3">Last run</th><th class="px-5 py-3">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($tasks as $task)
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-mono text-sm font-medium text-slate-950 dark:text-white">{{ $task['command'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $task['description'] }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $task['frequency'] }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $task['next_run'] ?? 'Unavailable' }}</td>
                                <td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ $task['last_run']?->started_at?->format('d M Y H:i') ?? 'Not tracked yet' }}</td>
                                <td class="px-5 py-4"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $statusClass($task['status']) }}">{{ str($task['status'])->headline() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="font-semibold text-slate-950 dark:text-white">Recent run history</h2></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($runs as $run)
                    <div class="grid gap-2 px-5 py-4 text-sm md:grid-cols-[1fr_120px_120px_1fr]">
                        <div><p class="font-mono font-medium text-slate-950 dark:text-white">{{ $run->command }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $run->description }}</p></div>
                        <div><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $statusClass($run->status) }}">{{ str($run->status)->headline() }}</span></div>
                        <div class="text-slate-600 dark:text-slate-300">{{ $run->duration_ms ? $run->duration_ms.' ms' : 'n/a' }}</div>
                        <div class="text-slate-500 dark:text-slate-400">{{ $run->failure_reason ?? $run->output ?? 'No output captured.' }}</div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No scheduled task runs have been recorded.</div>
                @endforelse
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $runs->links() }}</div>
        </section>
    </div>
@endsection

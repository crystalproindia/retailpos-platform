@extends('layouts.admin')

@section('title', 'Queue Monitor')
@section('page-title', 'Queue Monitor')

@section('breadcrumbs')
    <span>/</span><span>Operations</span><span>/</span><span>Queue Monitor</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.operations.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Queue Monitor</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Connection, queue depth, reserved work, and failed job counts from Laravel tables.</p>
                </div>
                @can('operations.settings.manage')
                    <form method="POST" action="{{ route('operations.queue.snapshot') }}">
                        @csrf
                        <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Capture snapshot</button>
                    </form>
                @endcan
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-5">
            @foreach ([
                ['label' => 'Connection', 'value' => $summary['connection']],
                ['label' => 'Driver', 'value' => $summary['driver']],
                ['label' => 'Pending', 'value' => number_format($summary['pending_count'])],
                ['label' => 'Reserved', 'value' => number_format($summary['reserved_count'])],
                ['label' => 'Failed', 'value' => number_format($summary['failed_count'])],
            ] as $metric)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $metric['label'] }}</p>
                    <p class="mt-2 truncate text-2xl font-semibold text-slate-950 dark:text-white">{{ $metric['value'] }}</p>
                </div>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="font-semibold text-slate-950 dark:text-white">Queue breakdown</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                            <tr><th class="px-5 py-3">Queue</th><th class="px-5 py-3">Pending</th><th class="px-5 py-3">Reserved</th><th class="px-5 py-3">Failed</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($breakdown as $queue)
                                <tr>
                                    <td class="px-5 py-4 font-medium text-slate-950 dark:text-white">{{ $queue['queue'] }}</td>
                                    <td class="px-5 py-4">{{ $queue['pending_count'] }}</td>
                                    <td class="px-5 py-4">{{ $queue['reserved_count'] }}</td>
                                    <td class="px-5 py-4">{{ $queue['failed_count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">No queued jobs currently present.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="font-semibold text-slate-950 dark:text-white">Recent snapshots</h2></div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($snapshots as $snapshot)
                        <div class="grid grid-cols-4 gap-3 px-5 py-4 text-sm">
                            <div class="font-medium text-slate-950 dark:text-white">{{ $snapshot->queue }}</div>
                            <div class="text-slate-600 dark:text-slate-300">{{ $snapshot->pending_count }} pending</div>
                            <div class="text-slate-600 dark:text-slate-300">{{ $snapshot->failed_count }} failed</div>
                            <div class="text-right text-slate-500 dark:text-slate-400">{{ $snapshot->captured_at->format('d M H:i') }}</div>
                        </div>
                    @empty
                        <div class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No queue snapshots captured yet.</div>
                    @endforelse
                </div>
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $snapshots->links() }}</div>
            </div>
        </section>
    </div>
@endsection

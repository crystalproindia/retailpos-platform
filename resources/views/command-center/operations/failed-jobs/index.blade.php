@extends('layouts.admin')

@section('title', 'Failed Jobs')
@section('page-title', 'Failed Jobs')

@section('breadcrumbs')
    <span>/</span><span>Operations</span><span>/</span><span>Failed Jobs</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.operations.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Failed Jobs</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Inspect safe queue payload summaries, retry jobs, or delete failed records.</p>

            <form method="GET" action="{{ route('operations.failed-jobs.index') }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_170px_170px_auto]">
                <input name="search" value="{{ request('search') }}" placeholder="Search uuid, payload, exception" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <select name="queue" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All queues</option>
                    @foreach ($queues as $queue)
                        <option value="{{ $queue }}" @selected(request('queue') === $queue)>{{ $queue }}</option>
                    @endforeach
                </select>
                <input name="connection" value="{{ request('connection') }}" placeholder="Connection" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Filter</button>
            </form>
        </section>

        @can('operations.failed_jobs.retry')
            <form id="failed-job-bulk-form" method="POST" action="{{ route('operations.failed-jobs.bulk-retry') }}">
                @csrf
            </form>
        @endcan

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $jobs->total() }} failed job{{ $jobs->total() === 1 ? '' : 's' }}</p>
                @can('operations.failed_jobs.retry')
                    <div class="flex gap-2">
                        <button form="failed-job-bulk-form" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Bulk retry</button>
                        <button form="failed-job-bulk-form" formaction="{{ route('operations.failed-jobs.bulk-destroy') }}" formmethod="POST" name="_method" value="DELETE" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950">Bulk delete</button>
                    </div>
                @endcan
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3"></th>
                            <th class="px-5 py-3">Job</th>
                            <th class="px-5 py-3">Queue</th>
                            <th class="px-5 py-3">Exception preview</th>
                            <th class="px-5 py-3">Failed</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($jobs as $job)
                            <tr>
                                <td class="px-5 py-4">@can('operations.failed_jobs.retry')<input form="failed-job-bulk-form" type="checkbox" name="ids[]" value="{{ $job['id'] }}" class="rounded border-slate-300">@endcan</td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $job['display_name'] }}</p>
                                    <p class="mt-1 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $job['uuid'] }}</p>
                                    <details class="mt-2">
                                        <summary class="cursor-pointer text-xs font-semibold text-teal-700 dark:text-teal-300">Payload summary</summary>
                                        <dl class="mt-2 grid gap-1 rounded-lg bg-slate-50 p-3 text-xs dark:bg-slate-950">
                                            @foreach ($job['payload_summary'] as $key => $value)
                                                <div class="grid grid-cols-[110px_1fr] gap-2">
                                                    <dt class="text-slate-500">{{ $key }}</dt>
                                                    <dd class="break-all text-slate-700 dark:text-slate-200">{{ is_array($value) ? implode(', ', $value) : ($value ?? 'n/a') }}</dd>
                                                </div>
                                            @endforeach
                                        </dl>
                                    </details>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $job['connection'] }} / {{ $job['queue'] }}</td>
                                <td class="max-w-md px-5 py-4 text-slate-600 dark:text-slate-300"><p class="line-clamp-4">{{ $job['exception_preview'] }}</p></td>
                                <td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ \Carbon\Carbon::parse($job['failed_at'])->format('d M Y H:i') }}</td>
                                <td class="px-5 py-4 text-right">
                                    @can('operations.failed_jobs.retry')
                                        <div class="flex justify-end gap-2">
                                            <form method="POST" action="{{ route('operations.failed-jobs.retry', $job['id']) }}">@csrf<button class="text-sm font-semibold text-teal-700 dark:text-teal-300">Retry</button></form>
                                            <form method="POST" action="{{ route('operations.failed-jobs.destroy', $job['id']) }}">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-700 dark:text-rose-300">Delete</button></form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">No failed jobs found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $jobs->links() }}</div>
        </section>
    </div>
@endsection

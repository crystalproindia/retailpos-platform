@extends('layouts.admin')

@section('title', 'System Health')
@section('page-title', 'System Health')

@section('breadcrumbs')
    <span>/</span><span>Operations</span><span>/</span><span>System Health</span>
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
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">System Health</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Application, infrastructure, runtime, queue, and delivery checks.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass($overallStatus) }}">{{ str($overallStatus)->headline() }}</span>
                    @can('operations.settings.manage')
                        <form method="POST" action="{{ route('operations.health.run') }}">
                            @csrf
                            <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Run checks</button>
                        </form>
                    @endcan
                </div>
            </div>

            <form method="GET" action="{{ route('operations.health.index') }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_160px_180px_auto]">
                <input name="search" value="{{ request('search') }}" placeholder="Search checks" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All statuses</option>
                    @foreach (['healthy', 'warning', 'critical', 'unknown'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                    @endforeach
                </select>
                <select name="category" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Filter</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Check</th>
                            <th class="px-5 py-3">Category</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Message</th>
                            <th class="px-5 py-3">Checked</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($checks as $check)
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $check->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $check->key }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $check->category }}</td>
                                <td class="px-5 py-4"><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $statusClass($check->status) }}">{{ str($check->status)->headline() }}</span></td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $check->message }}</td>
                                <td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ $check->checked_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">No health checks have been captured.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $checks->links() }}</div>
        </section>
    </div>
@endsection

@extends('layouts.admin')

@section('title', $title)
@section('page-title', $title)

@section('breadcrumbs')
    <span>/</span>
    <span>{{ $title }}</span>
@endsection

@section('content')
    <div class="space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <p class="text-sm font-medium text-teal-700 dark:text-teal-300">Command module</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-normal text-slate-950 dark:text-white">{{ $title }}</h1>
                </div>
                <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ auth()->user()->role->label() }}</span>
            </div>
        </section>

        @if ($module === 'audit-logs' && $auditLogs)
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Audit Logs</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                            <tr>
                                <th class="px-5 py-3">Event</th>
                                <th class="px-5 py-3">Description</th>
                                <th class="px-5 py-3">User</th>
                                <th class="px-5 py-3">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($auditLogs as $log)
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-4 font-medium text-slate-950 dark:text-white">{{ $log->event }}</td>
                                    <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $log->description }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-600 dark:text-slate-300">{{ $log->user?->name ?? 'System' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-500 dark:text-slate-400">{{ $log->created_at?->format('d M Y, h:i A') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">No audit logs recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">
                    {{ $auditLogs->links() }}
                </div>
            </section>
        @else
            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Company</p>
                    <p class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ auth()->user()->company?->name }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Branch</p>
                    <p class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ auth()->user()->branch?->name ?? 'Primary branch' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Access</p>
                    <p class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">{{ auth()->user()->role->label() }}</p>
                </div>
            </section>
        @endif
    </div>
@endsection

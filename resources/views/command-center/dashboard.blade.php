@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
    <div class="space-y-6">
        <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <p class="text-sm font-medium text-teal-700 dark:text-teal-300">{{ auth()->user()->company?->name }}</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-normal text-slate-950 sm:text-3xl dark:text-white">Command Center</h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">Today&apos;s retail operating snapshot across sales, inventory, teams, and growth channels.</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-slate-500 dark:text-slate-400">Signed in as</p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-white">{{ auth()->user()->role->label() }}</p>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($metrics as $metric)
                @php
                    $tone = match ($metric->tone) {
                        'success' => 'border-teal-200 bg-teal-50 text-teal-900 dark:border-teal-800 dark:bg-teal-950 dark:text-teal-100',
                        'warning' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100',
                        'danger' => 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-100',
                        default => 'border-slate-200 bg-white text-slate-950 dark:border-slate-800 dark:bg-slate-900 dark:text-white',
                    };
                @endphp

                <article class="rounded-lg border p-5 shadow-sm {{ $tone }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium opacity-75">{{ $metric->label }}</p>
                            <p class="mt-3 text-3xl font-semibold tracking-normal">{{ $metric->value }}</p>
                        </div>
                        <div class="grid size-10 place-items-center rounded-lg bg-white/70 text-slate-700 dark:bg-white/10 dark:text-slate-200">
                            <x-icon :name="$metric->key" class="size-5" />
                        </div>
                    </div>
                    @if ($metric->trend)
                        <p class="mt-4 text-sm font-medium opacity-75">{{ $metric->trend }}</p>
                    @endif
                </article>
            @endforeach
        </section>

        <section class="grid gap-6 xl:grid-cols-[1.4fr_0.8fr]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">Operating Focus</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Priority indicators from seeded demo data.</p>
                    </div>
                    <span class="rounded-md bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">Demo</span>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Low Stock</p>
                        <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ $metrics->firstWhere('key', 'low_stock')?->value ?? '0' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Leads</p>
                        <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ $metrics->firstWhere('key', 'leads')?->value ?? '0' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Branches</p>
                        <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ $metrics->firstWhere('key', 'branches')?->value ?? '0' }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Recent Audit Activity</h2>
                <div class="mt-5 space-y-3">
                    @forelse ($recentAuditLogs as $log)
                        <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                            <p class="text-sm font-medium text-slate-950 dark:text-white">{{ $log->description }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $log->created_at?->diffForHumans() }} by {{ $log->user?->name ?? 'System' }}</p>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No audit activity yet.</p>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
@endsection

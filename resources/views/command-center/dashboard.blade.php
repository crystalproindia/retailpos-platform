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
                    $displayValue = $metric->key === 'leads' && auth()->user()->can('crm.leads.view')
                        ? $leadMetrics['total_leads']
                        : $metric->value;
                @endphp

                <article class="rounded-lg border p-5 shadow-sm {{ $tone }}">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium opacity-75">{{ $metric->label }}</p>
                            <p class="mt-3 text-3xl font-semibold tracking-normal">{{ $displayValue }}</p>
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

        @can('crm.leads.view')
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">Lead Intake</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Current CRM enquiries, demo requests, and follow-up commitments.</p>
                    </div>
                    <a href="{{ route('crm.leads.index') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Open leads</a>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['label' => 'Total Leads', 'value' => $leadMetrics['total_leads'], 'tone' => 'bg-slate-50 dark:bg-slate-950'],
                        ['label' => 'New Leads', 'value' => $leadMetrics['new_leads'], 'tone' => 'bg-sky-50 dark:bg-sky-950/30'],
                        ['label' => 'Demo Requests', 'value' => $leadMetrics['demo_requests'], 'tone' => 'bg-violet-50 dark:bg-violet-950/30'],
                        ['label' => 'Follow-up Pending', 'value' => $leadMetrics['follow_up_pending'], 'tone' => 'bg-amber-50 dark:bg-amber-950/30'],
                    ] as $leadCard)
                        <a href="{{ $leadCard['label'] === 'Demo Requests' ? route('crm.demo-requests.index') : route('crm.leads.index') }}" class="rounded-lg border border-slate-200 p-4 transition hover:border-slate-300 hover:shadow-sm dark:border-slate-800 dark:hover:border-slate-700 {{ $leadCard['tone'] }}">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $leadCard['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $leadCard['value'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endcan

        @can('crm.demos.view')
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">Demo Schedule</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Keep scheduled sales conversations visible from the Command Center.</p>
                    </div>
                    <a href="{{ route('crm.demo-requests.index') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Open demo requests</a>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['label' => 'Demos Today', 'value' => $demoMetrics['demos_today'], 'tone' => 'bg-sky-50 dark:bg-sky-950/30'],
                        ['label' => 'Upcoming Demos', 'value' => $demoMetrics['upcoming_demos'], 'tone' => 'bg-teal-50 dark:bg-teal-950/30'],
                        ['label' => 'Demo Scheduled', 'value' => $demoMetrics['scheduled_demos'], 'tone' => 'bg-violet-50 dark:bg-violet-950/30'],
                        ['label' => 'Overdue Demo Follow-ups', 'value' => $demoMetrics['overdue_demos'], 'tone' => 'bg-amber-50 dark:bg-amber-950/30'],
                    ] as $demoCard)
                        <a href="{{ route('crm.demo-requests.index') }}" class="rounded-lg border border-slate-200 p-4 transition hover:border-slate-300 hover:shadow-sm dark:border-slate-800 dark:hover:border-slate-700 {{ $demoCard['tone'] }}">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $demoCard['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $demoCard['value'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>

            @include('command-center.crm.partials.upcoming-demos', [
                'heading' => 'Upcoming Demo Schedule',
                'emptyHeading' => 'No upcoming demos',
                'emptyMessage' => 'Scheduled demos will appear here as soon as your team confirms them.',
            ])
        @endcan

        @if ($cmsDashboard)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
                    <div>
                        <h2 class="text-base font-semibold text-slate-950 dark:text-white">Website Publishing</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A concise view of the content and SEO work ready for the public site.</p>
                    </div>
                    <a href="{{ route('cms.dashboard') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Open CMS</a>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        ['label' => 'Published Pages', 'value' => $cmsDashboard['counts']['published_pages'], 'route' => 'cms.seo-pages.index'],
                        ['label' => 'Draft Pages', 'value' => $cmsDashboard['counts']['draft_pages'], 'route' => 'cms.seo-pages.index'],
                        ['label' => 'Published Articles', 'value' => $cmsDashboard['counts']['published_articles'], 'route' => 'cms.articles.index'],
                        ['label' => 'Active Redirects', 'value' => $cmsDashboard['counts']['redirects'], 'route' => 'cms.redirects.index'],
                    ] as $cmsCard)
                        <a href="{{ route($cmsCard['route']) }}" class="rounded-lg border border-slate-200 bg-slate-50 p-4 transition hover:border-slate-300 hover:shadow-sm dark:border-slate-800 dark:bg-slate-950 dark:hover:border-slate-700">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $cmsCard['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $cmsCard['value'] }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

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
                        <p class="mt-2 text-xl font-semibold text-slate-950 dark:text-white">{{ auth()->user()->can('crm.leads.view') ? $leadMetrics['total_leads'] : '0' }}</p>
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

@php
    $items = [
        ['label' => 'Dashboard', 'route' => 'operations.dashboard', 'ability' => 'operations.view'],
        ['label' => 'System Health', 'route' => 'operations.health.index', 'ability' => 'operations.health.view'],
        ['label' => 'Queue Monitor', 'route' => 'operations.queue.index', 'ability' => 'operations.queue.view'],
        ['label' => 'Failed Jobs', 'route' => 'operations.failed-jobs.index', 'ability' => 'operations.failed_jobs.view'],
        ['label' => 'Schedule', 'route' => 'operations.schedule.index', 'ability' => 'operations.schedule.view'],
        ['label' => 'Application Info', 'route' => 'operations.application.index', 'ability' => 'operations.application.view'],
    ];
@endphp

<nav class="flex gap-2 overflow-x-auto rounded-lg border border-slate-200 bg-white p-2 text-sm shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Operations Monitor">
    @foreach ($items as $item)
        @can($item['ability'])
            <a href="{{ route($item['route']) }}"
                class="whitespace-nowrap rounded-md px-3 py-2 font-medium transition {{ request()->routeIs($item['route']) ? 'bg-slate-950 text-white dark:bg-teal-300 dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                {{ $item['label'] }}
            </a>
        @endcan
    @endforeach
</nav>

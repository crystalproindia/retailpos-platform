@php
    $links = [
        ['label' => 'Overview', 'route' => 'promotions.dashboard', 'can' => 'promotions.dashboard.view'],
        ['label' => 'Campaigns', 'route' => 'promotions.campaigns.index', 'can' => 'promotions.campaigns.view'],
        ['label' => 'Rules', 'route' => 'promotions.rules.index', 'can' => 'promotions.rules.view'],
        ['label' => 'Coupons', 'route' => 'promotions.coupons.index', 'can' => 'promotions.coupons.view'],
        ['label' => 'Simulator', 'route' => 'promotions.simulator.index', 'can' => 'promotions.simulator.view'],
        ['label' => 'Usage', 'route' => 'promotions.usage.index', 'can' => 'promotions.usage.view'],
        ['label' => 'Settings', 'route' => 'promotions.settings.index', 'can' => 'promotions.settings.manage'],
    ];
@endphp

<div class="mb-6 overflow-x-auto">
    <nav class="flex min-w-max gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Promotion sections">
        @foreach ($links as $link)
            @can($link['can'])
                <a href="{{ route($link['route']) }}" class="rounded-md px-3 py-2 text-sm font-medium transition {{ request()->routeIs($link['route']) ? 'bg-slate-950 text-white dark:bg-teal-300 dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">{{ $link['label'] }}</a>
            @endcan
        @endforeach
    </nav>
</div>

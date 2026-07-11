@php
    $items = [
        ['label' => 'Inbox', 'route' => 'notifications.index', 'ability' => 'notifications.manage_own'],
        ['label' => 'Preferences', 'route' => 'notifications.preferences.index', 'ability' => 'notifications.preferences.manage_own'],
        ['label' => 'Event Log', 'route' => 'notifications.events.index', 'ability' => 'notifications.events.view'],
        ['label' => 'Delivery Log', 'route' => 'notifications.deliveries.index', 'ability' => 'notifications.deliveries.view'],
        ['label' => 'Webhooks', 'route' => 'notifications.webhooks.index', 'ability' => 'notifications.webhooks.view'],
        ['label' => 'Templates', 'route' => 'notifications.templates.index', 'ability' => 'notifications.templates.manage'],
    ];
@endphp

<nav class="flex gap-2 overflow-x-auto rounded-lg border border-slate-200 bg-white p-2 text-sm shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Notification Center">
    @foreach ($items as $item)
        @can($item['ability'])
            <a href="{{ route($item['route']) }}"
                class="whitespace-nowrap rounded-md px-3 py-2 font-medium transition {{ request()->routeIs($item['route']) ? 'bg-slate-950 text-white dark:bg-teal-300 dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                {{ $item['label'] }}
            </a>
        @endcan
    @endforeach
</nav>

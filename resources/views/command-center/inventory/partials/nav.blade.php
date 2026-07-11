@php
    $links = [
        ['label' => 'Dashboard', 'route' => 'inventory.dashboard', 'can' => 'inventory.view'],
        ['label' => 'Products', 'route' => 'inventory.products.index', 'can' => 'inventory.products.view'],
        ['label' => 'Categories', 'route' => 'inventory.categories.index', 'can' => 'inventory.categories.manage'],
        ['label' => 'Brands', 'route' => 'inventory.brands.index', 'can' => 'inventory.brands.manage'],
        ['label' => 'Units', 'route' => 'inventory.units.index', 'can' => 'inventory.units.manage'],
        ['label' => 'Tax', 'route' => 'inventory.tax-rates.index', 'can' => 'inventory.tax.manage'],
        ['label' => 'Warehouses', 'route' => 'inventory.warehouses.index', 'can' => 'inventory.warehouses.manage'],
        ['label' => 'Ledger', 'route' => 'inventory.stock.ledger', 'can' => 'inventory.stock.view'],
        ['label' => 'Adjustments', 'route' => 'inventory.adjustments.index', 'can' => 'inventory.stock.adjust'],
        ['label' => 'Labels', 'route' => 'inventory.barcode-templates.index', 'can' => 'inventory.barcode.manage'],
        ['label' => 'Reorder', 'route' => 'inventory.reorder.index', 'can' => 'inventory.reorder.view'],
        ['label' => 'Channels', 'route' => 'inventory.channels.index', 'can' => 'inventory.channels.view'],
        ['label' => 'Settings', 'route' => 'inventory.settings.index', 'can' => 'inventory.settings.manage'],
    ];
@endphp

<div class="mb-6 overflow-x-auto">
    <nav class="flex min-w-max gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Inventory sections">
        @foreach ($links as $link)
            @can($link['can'])
                <a href="{{ route($link['route']) }}"
                    class="rounded-md px-3 py-2 text-sm font-medium transition {{ request()->routeIs($link['route']) ? 'bg-slate-950 text-white dark:bg-teal-300 dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                    {{ $link['label'] }}
                </a>
            @endcan
        @endforeach
    </nav>
</div>

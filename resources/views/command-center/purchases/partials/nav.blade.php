@php
    $links = [
        ['label' => 'Dashboard', 'route' => 'purchases.dashboard', 'can' => 'purchases.dashboard.view'],
        ['label' => 'Supplier Dashboard', 'route' => 'purchases.supplier-dashboard', 'can' => 'purchases.supplier_dashboard.view'],
        ['label' => 'Suppliers', 'route' => 'purchases.suppliers.index', 'can' => 'purchases.suppliers.view'],
        ['label' => 'Requests', 'route' => 'purchases.requests.index', 'can' => 'purchases.requests.view'],
        ['label' => 'Orders', 'route' => 'purchases.orders.index', 'can' => 'purchases.orders.view'],
        ['label' => 'GRN', 'route' => 'purchases.grn.index', 'can' => 'purchases.grn.view'],
        ['label' => 'Invoices', 'route' => 'purchases.invoices.index', 'can' => 'purchase-invoices.view'],
        ['label' => 'Payments', 'route' => 'purchases.payments.index', 'can' => 'supplier-payments.view'],
        ['label' => 'Reports', 'route' => 'purchases.reports.index', 'can' => 'purchase-reports.view'],
        ['label' => 'Input GST', 'route' => 'purchases.reports.input-gst', 'can' => 'input-gst-reports.view'],
        ['label' => 'Returns', 'route' => 'purchases.returns.index', 'can' => 'purchases.returns.view'],
        ['label' => 'Settings', 'route' => 'purchases.settings.index', 'can' => 'purchases.settings.manage'],
    ];
@endphp

<div class="mb-6 overflow-x-auto">
    <nav class="flex min-w-max gap-2 rounded-lg border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Purchase sections">
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

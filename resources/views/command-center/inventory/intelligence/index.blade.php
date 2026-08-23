@extends('layouts.admin')

@section('title', 'Inventory Intelligence')
@section('page-title', 'Inventory Intelligence')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Intelligence</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')

    @php
        $money = fn (int $minor) => 'INR '.number_format($minor / 100, 2);
        $cards = [
            ['label' => 'Current stock value', 'value' => $money($intelligence['cards']['stock_value_minor']), 'tone' => 'teal', 'filter' => null],
            ['label' => 'Units on hand', 'value' => number_format($intelligence['cards']['units_on_hand'], 3), 'tone' => 'sky', 'filter' => null],
            ['label' => 'Low stock', 'value' => number_format($intelligence['cards']['low_stock_count']), 'tone' => 'amber', 'filter' => 'low'],
            ['label' => 'Out of stock', 'value' => number_format($intelligence['cards']['out_of_stock_count']), 'tone' => 'rose', 'filter' => 'out'],
            ['label' => 'Dead stock value', 'value' => $money($intelligence['cards']['dead_stock_value_minor']), 'tone' => 'slate', 'filter' => 'dead'],
            ['label' => 'Slow stock value', 'value' => $money($intelligence['cards']['slow_stock_value_minor']), 'tone' => 'violet', 'filter' => 'slow'],
            ['label' => 'Fast products', 'value' => number_format($intelligence['cards']['fast_product_count']), 'tone' => 'emerald', 'filter' => 'fast'],
            ['label' => 'Recommended reorder', 'value' => $money($intelligence['cards']['reorder_value_minor']), 'tone' => 'indigo', 'filter' => 'reorder'],
        ];
        $tones = [
            'teal' => 'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900 dark:bg-teal-950/40 dark:text-teal-300',
            'sky' => 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300',
            'amber' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300',
            'rose' => 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300',
            'slate' => 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-200',
            'violet' => 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900 dark:bg-violet-950/40 dark:text-violet-300',
            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
            'indigo' => 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900 dark:bg-indigo-950/40 dark:text-indigo-300',
        ];
        $filterQuery = fn (array $extra = []) => array_filter(array_merge(request()->except('page'), $extra), fn ($value) => filled($value));
    @endphp

    <header class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-3xl">
            <p class="text-xs font-semibold uppercase text-teal-700 dark:text-teal-300">Decision support</p>
            <h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Know what to buy, move, and review</h1>
            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Deterministic recommendations from authorized stock, completed sales and returns, incoming purchases, and immutable profitability snapshots.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @can('inventory.reorder.view')
                <a href="{{ route('inventory.reorder.index') }}" class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">Reorder rules</a>
            @endcan
            @can('inventory.settings.manage')
                <a href="{{ route('inventory.settings.index') }}" class="rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Intelligence settings</a>
            @endcan
        </div>
    </header>

    <form method="GET" class="mb-6 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-9">
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Warehouse<select name="warehouse_id" class="mt-1 w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">All authorized</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(($filters['warehouse_id'] ?? null) == $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Category<select name="category_id" class="mt-1 w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? null) == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Brand<select name="brand_id" class="mt-1 w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">All brands</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected(($filters['brand_id'] ?? null) == $brand->id)>{{ $brand->name }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Product<select name="product_id" class="mt-1 w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? null) == $product->id)>{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Supplier<select name="supplier_id" class="mt-1 w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">All suppliers</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? null) == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Velocity window<select name="velocity_period" class="mt-1 w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@foreach([7,30,60,90] as $days)<option value="{{ $days }}" @selected($intelligence['period'] === $days)>{{ $days }} days</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Stock status<select name="stock_status" class="mt-1 w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">All statuses</option>@foreach(['low' => 'Low stock', 'out' => 'Out of stock', 'fast' => 'Fast moving', 'slow' => 'Slow moving', 'dead' => 'Dead stock', 'overstocked' => 'Overstocked', 'reorder' => 'Reorder'] as $value => $label)<option value="{{ $value }}" @selected(($filters['stock_status'] ?? null) === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Stock age<select name="aging_range" class="mt-1 w-full rounded-md border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">All ages</option>@foreach(['0_30' => '0-30 days', '31_60' => '31-60 days', '61_90' => '61-90 days', '91_180' => '91-180 days', '180_plus' => '180+ days', 'unknown' => 'Unknown'] as $value => $label)<option value="{{ $value }}" @selected(($filters['aging_range'] ?? null) === $value)>{{ $label }}</option>@endforeach</select></label>
        <div class="flex items-end gap-2"><button class="w-full rounded-md bg-teal-600 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-500">Apply</button><a href="{{ route('inventory.intelligence.index') }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Reset</a></div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach($cards as $card)
            <a href="{{ route('inventory.intelligence.index', $filterQuery($card['filter'] ? ['stock_status' => $card['filter']] : [])) }}" class="rounded-lg border p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $tones[$card['tone']] }}">
                <p class="text-xs font-semibold uppercase opacity-80">{{ $card['label'] }}</p>
                <p class="mt-3 text-2xl font-semibold text-slate-950 dark:text-white">{{ $card['value'] }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 p-5 dark:border-slate-800"><div><h2 class="font-semibold text-slate-950 dark:text-white">Stock value by age</h2><p class="mt-1 text-sm text-slate-500">Latest qualifying inbound movement approximation.</p></div><a href="{{ route('inventory.intelligence.export', ['dataset' => 'aging'] + $filterQuery()) }}" class="text-sm font-semibold text-teal-700 dark:text-teal-300">Export</a></div>
            <div class="space-y-4 p-5">
                @foreach($intelligence['aging'] as $bucket)
                    <div><div class="flex justify-between gap-3 text-sm"><span class="font-medium text-slate-700 dark:text-slate-200">{{ $bucket['label'] }}</span><span class="text-slate-500">{{ $money($bucket['value_minor']) }} · {{ number_format($bucket['percentage'], 1) }}%</span></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-teal-500" style="width: {{ min(100, $bucket['percentage']) }}%"></div></div></div>
                @endforeach
            </div>
        </section>
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="font-semibold text-slate-950 dark:text-white">Inventory value by category</h2><p class="mt-1 text-sm text-slate-500">Current on-hand quantity at authoritative product cost.</p></div>
            @php($maxCategory = max(1, (int) $intelligence['value_by_category']->max('value_minor')))
            <div class="space-y-4 p-5">@forelse($intelligence['value_by_category'] as $row)<div><div class="flex justify-between gap-3 text-sm"><span class="truncate font-medium text-slate-700 dark:text-slate-200">{{ $row['label'] }}</span><span class="shrink-0 text-slate-500">{{ $money($row['value_minor']) }}</span></div><div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-indigo-500" style="width: {{ max(2, ($row['value_minor'] / $maxCategory) * 100) }}%"></div></div></div>@empty<p class="py-10 text-center text-sm text-slate-500">No stock value is available.</p>@endforelse</div>
        </section>
    </div>

    @include('command-center.inventory.intelligence.partials.product-table', ['title' => 'Reorder & purchase recommendations', 'subtitle' => 'Review before creating any purchase document.', 'rows' => $intelligence['reorder'], 'dataset' => 'reorder', 'mode' => 'reorder'])
    @include('command-center.inventory.intelligence.partials.transfer-table', ['rows' => $intelligence['transfers']])

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        @include('command-center.inventory.intelligence.partials.ranked-list', ['title' => 'Fast moving', 'rows' => $intelligence['fast'], 'dataset' => 'fast', 'tone' => 'emerald'])
        @include('command-center.inventory.intelligence.partials.ranked-list', ['title' => 'Slow moving', 'rows' => $intelligence['slow'], 'dataset' => 'slow', 'tone' => 'amber'])
        @include('command-center.inventory.intelligence.partials.ranked-list', ['title' => 'Dead stock', 'rows' => $intelligence['dead'], 'dataset' => 'dead', 'tone' => 'rose'])
    </div>

    @include('command-center.inventory.intelligence.partials.stock-detail', ['rows' => $intelligence['rows']])

    <aside class="mt-6 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-200"><strong>How decisions are calculated:</strong> {{ $intelligence['methodology'] }} Recommendations never post stock, create transfers, or place orders automatically.</aside>
@endsection

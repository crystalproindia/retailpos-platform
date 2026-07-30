@extends('layouts.admin')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('content')
<div class="space-y-6">
    <form class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-5 dark:border-slate-800 dark:bg-slate-900">
        <select name="outlet_id" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Current outlet</option>@if($canViewAllOutlets)<option value="all" @selected(request('outlet_id') === 'all')>All outlets</option>@endif @foreach($outlets as $outlet)<option value="{{ $outlet->id }}" @selected((string) request('outlet_id') === (string) $outlet->id)>{{ $outlet->name }}</option>@endforeach</select>
        <select name="warehouse_id" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All warehouses</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select>
        <input type="date" name="date_from" value="{{ request('date_from', $overview['range']['from']->toDateString()) }}" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
        <input type="date" name="date_to" value="{{ request('date_to', $overview['range']['to']->toDateString()) }}" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
        <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Apply filters</button>
    </form>
    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $overview['scope']['label'] }} · {{ $overview['range']['timezone'] }} · {{ $overview['range']['from']->toDateString() }} to {{ $overview['range']['to']->toDateString() }}</p>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(['gross_sales'=>'Gross sales','net_sales'=>'Net sales','purchase_total'=>'Purchases','payments_received'=>'Payments received','outstanding_receivables'=>'Receivables','return_value'=>'Purchase returns','stock_value'=>'Current stock value','low_stock_count'=>'Low-stock items'] as $key=>$label)
        <a href="{{ route('reports.show', [$key === 'purchase_total' ? 'purchases' : ($key === 'stock_value' || $key === 'low_stock_count' ? 'inventory' : ($key === 'payments_received' ? 'payments' : ($key === 'outstanding_receivables' ? 'outstanding' : ($key === 'return_value' ? 'returns' : 'sales'))))] + request()->query()) }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $key === 'low_stock_count' ? $overview['metrics'][$key] : number_format($overview['metrics'][$key] / 100, 2) }}</p>
        </a>
        @endforeach
    </div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach(['sales'=>'Sales','purchases'=>'Purchases','inventory'=>'Inventory','profitability'=>'Gross Profit','gst'=>'GST & Tax','payments'=>'Payments','outstanding'=>'Outstanding','returns'=>'Returns','outlets'=>'Outlet Performance','cashiers'=>'Cashier Performance'] as $key=>$label)
        <a href="{{ route('reports.show', [$key] + request()->query()) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm hover:border-slate-400 dark:border-slate-800 dark:bg-slate-900"><h2 class="font-semibold text-slate-950 dark:text-white">{{ $label }}</h2><p class="mt-1 text-sm text-slate-500">Authorized, filter-consistent reporting with CSV export.</p></a>
        @endforeach
    </div>
</div>
@endsection

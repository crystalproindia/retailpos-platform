@extends('layouts.admin')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('content')
<div class="space-y-6">
    @include('command-center.reports.partials.filters', ['action' => route('reports.index')])
    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $overview['scope']['label'] }} · {{ $overview['range']['timezone'] }} · {{ $overview['range']['from']->toDateString() }} to {{ $overview['range']['to']->toDateString() }}</p>
    @php($metricReports = ['gross_sales' => 'sales', 'net_sales' => 'sales', 'purchase_total' => 'purchases', 'payments_received' => 'payments', 'outstanding_receivables' => 'outstanding', 'return_value' => 'returns', 'sales_return_value' => 'sales_returns', 'stock_value' => 'inventory', 'low_stock_count' => 'inventory'])
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(['gross_sales'=>'Gross sales','net_sales'=>'Net sales','purchase_total'=>'Purchases','payments_received'=>'Payments received','outstanding_receivables'=>'Receivables','return_value'=>'Purchase returns','sales_return_value'=>'Sales returns','stock_value'=>'Current stock value','low_stock_count'=>'Low-stock items'] as $key=>$label)
        <a href="{{ route('reports.show', [$metricReports[$key]] + request()->query()) }}" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $key === 'low_stock_count' ? $overview['metrics'][$key] : number_format($overview['metrics'][$key] / 100, 2) }}</p>
        </a>
        @endforeach
    </div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach(['sales'=>'Sales','purchases'=>'Purchases','inventory'=>'Inventory','movements'=>'Stock Movements','gst'=>'GST & Tax','payments'=>'Payments','outstanding'=>'Outstanding','returns'=>'Purchase Returns','sales_returns'=>'Sales Returns','outlets'=>'Outlet Performance','cashiers'=>'Cashier Performance'] as $key=>$label)
        <a href="{{ route('reports.show', [$key] + request()->query()) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm hover:border-slate-400 dark:border-slate-800 dark:bg-slate-900"><h2 class="font-semibold text-slate-950 dark:text-white">{{ $label }}</h2><p class="mt-1 text-sm text-slate-500">Authorized, filter-consistent reporting with CSV export.</p></a>
        @endforeach
        @can('reports.profitability.view')
            <a href="{{ route('reports.show', ['profitability'] + request()->query()) }}" class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm hover:border-emerald-400 dark:border-emerald-900/70 dark:bg-emerald-950/20"><h2 class="font-semibold text-slate-950 dark:text-white">Gross Profit</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Tax-exclusive sale-time cost snapshots with controlled access.</p></a>
        @endcan
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('title', 'Stock Ledger')
@section('page-title', 'Stock Ledger')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Ledger</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')

    <form method="GET" class="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5 dark:border-slate-800 dark:bg-slate-900">
        <select name="product_id" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->name }}</option>@endforeach</select>
        <select name="warehouse_id" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All warehouses</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(request('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select>
        <select name="movement_type" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All movements</option>@foreach(['opening','adjustment'] as $type)<option value="{{ $type }}" @selected(request('movement_type') === $type)>{{ str($type)->headline() }}</option>@endforeach</select>
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
        <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Filter</button>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400"><tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Product</th><th class="px-5 py-3">Warehouse</th><th class="px-5 py-3">Type</th><th class="px-5 py-3 text-right">Before</th><th class="px-5 py-3 text-right">After</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($movements as $movement)
                        <tr><td class="px-5 py-3">{{ $movement->occurred_at?->format('d M Y H:i') }}</td><td class="px-5 py-3 font-semibold">{{ $movement->product?->name }}</td><td class="px-5 py-3">{{ $movement->warehouse?->name }}</td><td class="px-5 py-3">{{ str($movement->movement_type)->headline() }}</td><td class="px-5 py-3 text-right">{{ $movement->quantity_before }}</td><td class="px-5 py-3 text-right">{{ $movement->quantity_after }}</td></tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">No stock movements found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $movements->links() }}</div>
    </div>
@endsection

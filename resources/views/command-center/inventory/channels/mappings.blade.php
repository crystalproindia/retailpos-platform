@extends('layouts.admin')

@section('title', 'Channel Product Mapping')
@section('page-title', 'Channel Product Mapping')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Channel Mapping</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <form method="POST" action="{{ route('inventory.channel-mappings.store') }}" class="mb-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        <div class="grid gap-4 md:grid-cols-3">
            <select name="sales_channel_id" required class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach($channels as $channel)<option value="{{ $channel->id }}">{{ $channel->name }}</option>@endforeach</select>
            <select name="product_id" required class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select>
            <select name="warehouse_id" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All warehouses</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select>
            <input name="channel_sku" placeholder="Channel SKU" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
            <input name="channel_price" type="number" step="0.01" min="0" placeholder="Channel price" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
            <input name="available_quantity" type="number" step="0.001" min="0" placeholder="Listed available qty" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
        </div>
        <div class="mt-4 flex justify-end"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save mapping</button></div>
    </form>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400"><tr><th class="px-5 py-3">Channel</th><th class="px-5 py-3">Product</th><th class="px-5 py-3">Channel SKU</th><th class="px-5 py-3">Sync</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@forelse($mappings as $mapping)<tr><td class="px-5 py-3">{{ $mapping->salesChannel?->name }}</td><td class="px-5 py-3 font-semibold">{{ $mapping->product?->name }}</td><td class="px-5 py-3 font-mono text-xs">{{ $mapping->channel_sku }}</td><td class="px-5 py-3">{{ str($mapping->sync_status)->headline() }}</td></tr>@empty<tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No channel mappings yet.</td></tr>@endforelse</tbody></table>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $mappings->links() }}</div>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'Products')
@section('page-title', 'Products')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Products</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')

    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <form class="grid gap-3 md:grid-cols-[1fr_auto_auto_auto]" method="GET">
            <input name="search" value="{{ request('search') }}" placeholder="Search name, SKU, barcode or HSN" class="rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <select name="category_id" class="rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(request('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <select name="brand_id" class="rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <option value="">All brands</option>
                @foreach ($brands as $brand)
                    <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Filter</button>
        </form>
        @can('inventory.products.create')
            <a href="{{ route('inventory.products.create') }}" class="inline-flex items-center justify-center rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700">New product</a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Product</th>
                        <th class="px-5 py-3">SKU</th>
                        <th class="px-5 py-3">Category</th>
                        <th class="px-5 py-3 text-right">Price</th>
                        <th class="px-5 py-3 text-right">Available</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($products as $product)
                        <tr>
                            <td class="px-5 py-3">
                                <a href="{{ route('inventory.products.show', $product) }}" class="font-semibold text-slate-950 hover:text-teal-700 dark:text-white">{{ $product->name }}</a>
                                <p class="text-xs text-slate-500">{{ $product->brand?->name ?? 'No brand' }}</p>
                            </td>
                            <td class="px-5 py-3 font-mono text-xs">{{ $product->sku }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $product->category?->name ?? 'Unassigned' }}</td>
                            <td class="px-5 py-3 text-right">₹{{ number_format((float) $product->selling_price, 2) }}</td>
                            <td class="px-5 py-3 text-right">{{ $product->stockLevels->sum('quantity_available') }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $product->is_active ? 'bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-200' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ str($product->status)->headline() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">No products found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $products->links() }}</div>
    </div>
@endsection

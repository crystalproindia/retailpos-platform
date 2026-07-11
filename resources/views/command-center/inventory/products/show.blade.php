@extends('layouts.admin')

@section('title', $product->name)
@section('page-title', $product->name)
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><a href="{{ route('inventory.products.index') }}" class="hover:text-slate-950 dark:hover:text-white">Products</a><span>/</span><span>{{ $product->sku }}</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')

    <div class="grid gap-6 xl:grid-cols-[1fr_0.8fr]">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div>
                    <p class="font-mono text-xs text-slate-500">{{ $product->sku }}</p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">{{ $product->name }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $product->description ?: 'No description yet.' }}</p>
                </div>
                @can('inventory.products.update')
                    <a href="{{ route('inventory.products.edit', $product) }}" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Edit</a>
                @endcan
            </div>
            <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    'Barcode' => $product->barcode ?: 'Not set',
                    'Category' => $product->category?->name ?: 'Unassigned',
                    'Brand' => $product->brand?->name ?: 'Unassigned',
                    'Unit' => $product->unit?->short_code,
                    'HSN' => $product->hsn_code ?: 'Not set',
                    'Tax' => $product->taxRate?->name ?: 'None',
                ] as $label => $value)
                    <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt>
                        <dd class="mt-1 text-sm font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold">Stock by location</h2>
            <div class="mt-4 space-y-3">
                @forelse ($product->stockLevels as $stock)
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <p class="font-medium">{{ $stock->warehouse?->name }} @if($stock->location) / {{ $stock->location->code }} @endif</p>
                        <div class="mt-2 grid grid-cols-3 gap-2 text-sm text-slate-500">
                            <span>On hand: <strong class="text-slate-900 dark:text-white">{{ $stock->quantity_on_hand }}</strong></span>
                            <span>Reserved: <strong class="text-slate-900 dark:text-white">{{ $stock->quantity_reserved }}</strong></span>
                            <span>Available: <strong class="text-slate-900 dark:text-white">{{ $stock->quantity_available }}</strong></span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No stock recorded yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection

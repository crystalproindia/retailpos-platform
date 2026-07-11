@extends('layouts.admin')

@section('title', 'Opening Stock')
@section('page-title', 'Opening Stock')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Opening Stock</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <form method="POST" action="{{ route('inventory.opening-stock.store') }}" class="max-w-3xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <label class="space-y-1"><span class="text-sm font-medium">Product</span><select name="product_id" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select></label>
            <label class="space-y-1"><span class="text-sm font-medium">Warehouse</span><select name="warehouse_id" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></label>
            <label class="space-y-1"><span class="text-sm font-medium">Location</span><select name="stock_location_id" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">No bin</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->warehouse?->code }} / {{ $location->code }}</option>@endforeach</select></label>
            <label class="space-y-1"><span class="text-sm font-medium">Opening quantity</span><input name="quantity" type="number" step="0.001" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">Unit cost</span><input name="unit_cost" type="number" step="0.01" min="0" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1 md:col-span-2"><span class="text-sm font-medium">Notes</span><textarea name="notes" rows="3" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></textarea></label>
        </div>
        @if ($errors->any())<div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <div class="mt-6 flex justify-end"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Record opening stock</button></div>
    </form>
@endsection

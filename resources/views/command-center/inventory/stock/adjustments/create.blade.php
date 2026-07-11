@extends('layouts.admin')

@section('title', 'New Stock Adjustment')
@section('page-title', 'New Stock Adjustment')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><a href="{{ route('inventory.adjustments.index') }}" class="hover:text-slate-950 dark:hover:text-white">Adjustments</a>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <form method="POST" action="{{ route('inventory.adjustments.store') }}" class="max-w-5xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        <div class="grid gap-4 md:grid-cols-3">
            <label class="space-y-1"><span class="text-sm font-medium">Warehouse</span><select name="warehouse_id" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></label>
            <label class="space-y-1 md:col-span-2"><span class="text-sm font-medium">Reason</span><input name="reason" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
        </div>
        <div class="mt-5 rounded-lg border border-slate-200 dark:border-slate-800">
            <div class="grid gap-3 border-b border-slate-200 p-3 text-xs font-semibold uppercase tracking-wide text-slate-500 md:grid-cols-[1fr_1fr_140px_1fr] dark:border-slate-800"><span>Product</span><span>Location</span><span>Adjusted qty</span><span>Reason</span></div>
            @for ($i = 0; $i < 3; $i++)
                <div class="grid gap-3 p-3 md:grid-cols-[1fr_1fr_140px_1fr]">
                    <select name="items[{{ $i }}][product_id]" @required($i === 0) class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select>
                    <select name="items[{{ $i }}][stock_location_id]" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">No bin</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->warehouse?->code }} / {{ $location->code }}</option>@endforeach</select>
                    <input name="items[{{ $i }}][adjusted_quantity]" type="number" step="0.001" @required($i === 0) class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                    <input name="items[{{ $i }}][reason]" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                </div>
            @endfor
        </div>
        @if ($errors->any())<div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <div class="mt-6 flex justify-end"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Create draft</button></div>
    </form>
@endsection

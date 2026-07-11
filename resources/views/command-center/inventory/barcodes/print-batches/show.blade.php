@extends('layouts.admin')

@section('title', $batch->batch_number)
@section('page-title', $batch->batch_number)
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><a href="{{ route('inventory.barcode-batches.index') }}" class="hover:text-slate-950 dark:hover:text-white">Barcode Batches</a>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <p class="text-sm text-slate-500">{{ $batch->template?->name }} / {{ $batch->total_labels }} labels</p>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($batch->items as $item)
                <div class="rounded-lg border border-dashed border-slate-300 p-4 text-center dark:border-slate-700">
                    <p class="text-xs font-semibold">{{ $item->product?->name }}</p>
                    <div class="mx-auto mt-2 h-10 w-36 bg-[repeating-linear-gradient(90deg,#0f172a_0_2px,transparent_2px_4px,#0f172a_4px_5px,transparent_5px_8px)]"></div>
                    <p class="mt-1 font-mono text-[10px]">{{ $item->product?->barcode ?? $item->product?->sku }}</p>
                    <p class="mt-1 text-xs font-bold">₹{{ number_format((float) ($item->price_override ?? $item->product?->selling_price), 2) }}</p>
                    <p class="mt-2 text-xs text-slate-500">Qty: {{ $item->quantity }}</p>
                </div>
            @endforeach
        </div>
    </div>
@endsection

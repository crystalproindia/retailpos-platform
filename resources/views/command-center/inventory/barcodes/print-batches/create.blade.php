@extends('layouts.admin')

@section('title', 'New Barcode Batch')
@section('page-title', 'New Barcode Batch')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><a href="{{ route('inventory.barcode-batches.index') }}" class="hover:text-slate-950 dark:hover:text-white">Barcode Batches</a>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <form method="POST" action="{{ route('inventory.barcode-batches.store') }}" class="max-w-5xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <label class="space-y-1"><span class="text-sm font-medium">Template</span><select name="template_id" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach($templates as $template)<option value="{{ $template->id }}">{{ $template->name }}</option>@endforeach</select></label>
            <label class="space-y-1"><span class="text-sm font-medium">Title</span><input name="title" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-[1fr_140px_160px]">
            <select name="items[0][product_id]" required class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} ({{ $product->sku }})</option>@endforeach</select>
            <input name="items[0][quantity]" type="number" value="1" min="1" required class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
            <input name="items[0][price_override]" type="number" step="0.01" min="0" placeholder="Price override" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
        </div>
        @if ($errors->any())<div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <div class="mt-6 flex justify-end"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Create batch</button></div>
    </form>
@endsection

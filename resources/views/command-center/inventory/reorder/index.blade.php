@extends('layouts.admin')

@section('title', 'Reorder Suggestions')
@section('page-title', 'Reorder Suggestions')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Reorder</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')

    <div class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
        <form method="POST" action="{{ route('inventory.reorder.rules.store') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            <h2 class="text-base font-semibold">New reorder rule</h2>
            <div class="mt-4 grid gap-4">
                <label class="space-y-1"><span class="text-sm font-medium">Product</span><select name="product_id" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></label>
                <label class="space-y-1"><span class="text-sm font-medium">Warehouse</span><select name="warehouse_id" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All warehouses</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (['minimum_stock' => 'Minimum stock', 'maximum_stock' => 'Maximum stock', 'reorder_point' => 'Reorder point', 'reorder_quantity' => 'Reorder quantity', 'safety_stock' => 'Safety stock', 'average_daily_sales' => 'Avg daily sales'] as $field => $label)
                        <label class="space-y-1"><span class="text-sm font-medium">{{ $label }}</span><input name="{{ $field }}" type="number" step="0.001" @required(in_array($field, ['minimum_stock', 'reorder_point', 'reorder_quantity'], true)) class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
                    @endforeach
                </div>
                <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save and evaluate</button>
            </div>
            @if ($errors->any())<div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        </form>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="text-base font-semibold">Suggestions</h2></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400"><tr><th class="px-5 py-3">Product</th><th class="px-5 py-3">Risk</th><th class="px-5 py-3 text-right">Available</th><th class="px-5 py-3 text-right">Suggest</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($suggestions as $suggestion)
                            <tr><td class="px-5 py-3 font-semibold">{{ $suggestion->product?->name }}</td><td class="px-5 py-3">{{ str($suggestion->stockout_risk_level)->headline() }}</td><td class="px-5 py-3 text-right">{{ $suggestion->available_stock }}</td><td class="px-5 py-3 text-right">{{ $suggestion->suggested_quantity }}</td><td class="px-5 py-3 text-right"><div class="inline-flex gap-2">@if($suggestion->status === 'pending')<form method="POST" action="{{ route('inventory.reorder.suggestions.review', $suggestion) }}">@csrf<button class="text-sm font-semibold text-teal-700">Review</button></form><form method="POST" action="{{ route('inventory.reorder.suggestions.dismiss', $suggestion) }}">@csrf<button class="text-sm font-semibold text-rose-600">Dismiss</button></form>@else<span class="text-xs text-slate-500">{{ str($suggestion->status)->headline() }}</span>@endif</div></td></tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No reorder suggestions yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $suggestions->links() }}</div>
        </section>
    </div>
@endsection

@extends('layouts.admin')

@section('title', $adjustment->adjustment_number)
@section('page-title', $adjustment->adjustment_number)
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><a href="{{ route('inventory.adjustments.index') }}" class="hover:text-slate-950 dark:hover:text-white">Adjustments</a>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div><p class="text-sm text-slate-500">{{ $adjustment->warehouse?->name }}</p><h1 class="text-2xl font-semibold">{{ $adjustment->adjustment_number }}</h1><p class="mt-2 text-sm">{{ $adjustment->reason }}</p></div>
            @can('inventory.stock.approve_adjustment')
                @if ($adjustment->status === 'draft')
                    <form method="POST" action="{{ route('inventory.adjustments.approve', $adjustment) }}">@csrf<button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">Approve</button></form>
                @endif
            @endcan
        </div>
        <div class="mt-6 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400"><tr><th class="px-5 py-3">Product</th><th class="px-5 py-3">Location</th><th class="px-5 py-3 text-right">Current</th><th class="px-5 py-3 text-right">Adjusted</th><th class="px-5 py-3 text-right">Diff</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">@foreach($adjustment->items as $item)<tr><td class="px-5 py-3 font-semibold">{{ $item->product?->name }}</td><td class="px-5 py-3">{{ $item->location?->code ?? 'No bin' }}</td><td class="px-5 py-3 text-right">{{ $item->current_quantity }}</td><td class="px-5 py-3 text-right">{{ $item->adjusted_quantity }}</td><td class="px-5 py-3 text-right">{{ $item->difference }}</td></tr>@endforeach</tbody>
            </table>
        </div>
    </section>
@endsection

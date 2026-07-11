@extends('layouts.admin')

@section('title', 'Inventory')
@section('page-title', 'Inventory')
@section('breadcrumbs')
    <span>/</span><span>Inventory</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($dashboard['cards'] as $card)
            @php
                $tone = match ($card['tone']) {
                    'success' => 'border-teal-200 bg-teal-50 text-teal-900 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-100',
                    'warning' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100',
                    'danger' => 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100',
                    default => 'border-slate-200 bg-white text-slate-950 dark:border-slate-800 dark:bg-slate-900 dark:text-white',
                };
            @endphp
            <div class="rounded-lg border p-5 shadow-sm {{ $tone }}">
                <p class="text-sm font-medium opacity-75">{{ $card['label'] }}</p>
                <p class="mt-3 text-3xl font-semibold">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_1.4fr]">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Inventory value</p>
            <p class="mt-2 text-3xl font-semibold text-slate-950 dark:text-white">₹{{ number_format($dashboard['inventory_value'], 2) }}</p>
            <div class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                    <p class="text-slate-500 dark:text-slate-400">Categories</p>
                    <p class="mt-1 text-xl font-semibold">{{ $dashboard['categories'] }}</p>
                </div>
                <div class="rounded-lg bg-slate-50 p-4 dark:bg-slate-800">
                    <p class="text-slate-500 dark:text-slate-400">Brands</p>
                    <p class="mt-1 text-xl font-semibold">{{ $dashboard['brands'] }}</p>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Recent stock positions</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Product</th>
                            <th class="px-5 py-3">Warehouse</th>
                            <th class="px-5 py-3 text-right">On hand</th>
                            <th class="px-5 py-3 text-right">Available</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($dashboard['recentStock'] as $stock)
                            <tr>
                                <td class="px-5 py-3 font-medium">{{ $stock->product?->name }}</td>
                                <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $stock->warehouse?->name }}</td>
                                <td class="px-5 py-3 text-right">{{ $stock->quantity_on_hand }}</td>
                                <td class="px-5 py-3 text-right">{{ $stock->quantity_available }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No stock levels recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

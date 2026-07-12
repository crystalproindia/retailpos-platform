@extends('layouts.admin')

@section('title', 'Inventory Decision Dashboard')
@section('page-title', 'Inventory Decision Dashboard')
@section('breadcrumbs')
    <span>/</span><span>Inventory</span><span>/</span><span>Decision Dashboard</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($dashboard['cards'] as $card)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-white">{{ $card['value'] }}</p>
            </section>
        @endforeach
    </div>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Product stock decisions</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Expected sales days are calculated from available stock and average daily sales. Rows without sales history stay explicitly labelled.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Product</th>
                        <th class="px-5 py-3">Warehouse</th>
                        <th class="px-5 py-3 text-right">Available</th>
                        <th class="px-5 py-3">Expected sales days</th>
                        <th class="px-5 py-3">Supplier</th>
                        <th class="px-5 py-3">Decision</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($dashboard['rows'] as $row)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $row['stock_level']->product?->name }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $row['stock_level']->warehouse?->name }}</td>
                            <td class="px-5 py-3 text-right">{{ $row['available'] }}</td>
                            <td class="px-5 py-3">{{ $row['expected_sales_label'] }}</td>
                            <td class="px-5 py-3">{{ $row['preferred_supplier']?->supplier?->name ?? 'No preferred supplier' }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $row['risk'] === 'stockout' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-100' : ($row['risk'] === 'reorder' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-100' : 'bg-teal-100 text-teal-800 dark:bg-teal-950 dark:text-teal-100') }}">
                                    {{ str($row['risk'])->headline() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">No stock levels available for decisioning.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

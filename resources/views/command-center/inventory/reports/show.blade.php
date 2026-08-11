@extends('layouts.admin')

@section('title', 'Inventory Reports')
@section('page-title', 'Inventory Reports')

@section('content')
@include('command-center.inventory.partials.nav')

<div class="mx-auto max-w-7xl space-y-5">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold">{{ str($report)->replace('-', ' ')->headline() }}</h2>
            <p class="mt-1 text-sm text-slate-500">Authorized warehouse data only. Screen and CSV use the same bounded row provider.</p>
        </div>
        @can('inventory.reports.export')
            <a href="{{ route('inventory.reports.export', ['report' => $report] + request()->query()) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-4 text-sm font-semibold">Export CSV</a>
        @endcan
    </div>

    <nav aria-label="Inventory report types" class="flex gap-2 overflow-x-auto pb-1">
        @foreach($reportTypes as $type)
            <a href="{{ route('inventory.reports.show', $type) }}" class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold {{ $report === $type ? 'bg-slate-950 text-white dark:bg-teal-300 dark:text-slate-950' : 'border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900' }}">
                {{ str($type)->replace('-', ' ')->headline() }}
            </a>
        @endforeach
    </nav>

    <form method="GET" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-[1.2fr_1.2fr_1fr_1fr_auto] dark:border-slate-800 dark:bg-slate-900">
        <label class="text-xs font-semibold text-slate-500">
            Location
            <select name="warehouse_id" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">All authorized locations</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-slate-500">
            Product
            <select name="product_id" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                <option value="">All products</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-semibold text-slate-500">
            From date
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
        </label>
        <label class="text-xs font-semibold text-slate-500">
            To date
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
        </label>
        <button class="min-h-11 self-end rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Apply</button>
    </form>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            @if($rows->isNotEmpty())
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-950">
                        <tr>
                            @foreach(array_keys($rows->first()) as $column)
                                <th class="whitespace-nowrap px-4 py-3">{{ str($column)->replace('_', ' ')->headline() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($rows as $row)
                            <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                                @foreach($row as $value)
                                    <td class="whitespace-nowrap px-4 py-3">{{ $value ?? '-' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="p-12 text-center text-sm text-slate-500">No matching report rows.</div>
            @endif
        </div>
        @if($rows->count() >= 500)
            <div class="border-t border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Showing the first 500 rows. Narrow the location, product, or date filters for a smaller export.</div>
        @endif
    </section>
</div>
@endsection

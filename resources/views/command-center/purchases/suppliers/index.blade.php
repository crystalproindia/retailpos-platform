@extends('layouts.admin')

@section('title', 'Suppliers')
@section('page-title', 'Suppliers')
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>Suppliers</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <form method="GET" class="flex flex-1 flex-col gap-2 sm:flex-row">
            <input name="search" value="{{ request('search') }}" placeholder="Search suppliers" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            <select name="supplier_type" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                <option value="">All types</option>
                @foreach ($supplierTypes as $type)
                    <option value="{{ $type->value }}" @selected(request('supplier_type') === $type->value)>{{ str($type->value)->headline() }}</option>
                @endforeach
            </select>
            <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Filter</button>
        </form>
        <a href="{{ route('purchases.suppliers.create') }}" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">New supplier</a>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Supplier</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Contact</th>
                        <th class="px-5 py-3 text-right">Products</th>
                        <th class="px-5 py-3 text-right">Score</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td class="px-5 py-3">
                                <a href="{{ route('purchases.suppliers.show', $supplier) }}" class="font-medium text-slate-950 dark:text-white">{{ $supplier->name }}</a>
                                <p class="text-xs text-slate-500">{{ $supplier->code }}</p>
                            </td>
                            <td class="px-5 py-3">{{ str($supplier->supplier_type->value)->headline() }}</td>
                            <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $supplier->email ?: $supplier->phone }}</td>
                            <td class="px-5 py-3 text-right">{{ $supplier->products_count }}</td>
                            <td class="px-5 py-3 text-right">{{ $supplier->rating ? number_format((float) $supplier->rating, 1) : 'No score' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No suppliers found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $suppliers->links() }}</div>
    </section>
@endsection

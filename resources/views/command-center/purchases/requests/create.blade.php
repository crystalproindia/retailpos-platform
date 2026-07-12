@extends('layouts.admin')

@section('title', 'New Purchase Request')
@section('page-title', 'New Purchase Request')
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>Requests</span><span>/</span><span>Create</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <form method="POST" action="{{ route('purchases.requests.store') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        <div class="grid gap-4 md:grid-cols-3">
            <label class="text-sm font-medium">Warehouse
                <select name="warehouse_id" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Any warehouse</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">Priority
                <select name="priority" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}">{{ str($priority->value)->headline() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">Expected by
                <input name="expected_by" type="date" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            </label>
        </div>
        <label class="mt-4 block text-sm font-medium">Notes
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950"></textarea>
        </label>

        <div class="mt-6 rounded-lg border border-slate-200 dark:border-slate-800">
            <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold dark:border-slate-800">Request item</div>
            <div class="grid gap-4 p-4 md:grid-cols-5">
                <select name="items[0][product_id]" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                    <option value="">Product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
                <select name="items[0][supplier_id]" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Supplier optional</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
                <input name="items[0][requested_quantity]" type="number" min="0.001" step="0.001" placeholder="Quantity" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                <input name="items[0][estimated_price]" type="number" min="0" step="0.01" placeholder="Estimated price" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                <input name="items[0][notes]" placeholder="Notes" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('purchases.requests.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Cancel</a>
            <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Create request</button>
        </div>
    </form>
@endsection

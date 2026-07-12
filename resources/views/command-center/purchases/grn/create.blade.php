@extends('layouts.admin')

@section('title', 'New GRN')
@section('page-title', 'New GRN')
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>GRN</span><span>/</span><span>Create</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <form method="POST" action="{{ route('purchases.grn.store') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        <div class="grid gap-4 md:grid-cols-3">
            <label class="text-sm font-medium">Purchase order
                <select name="purchase_order_id" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Receive without PO if enabled</option>
                    @foreach ($orders as $order)
                        <option value="{{ $order->id }}">{{ $order->po_number }} · {{ $order->supplier?->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">Supplier
                <select name="supplier_id" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                    <option value="">From PO</option>
                    @foreach ($suppliers as $supplier)
                        <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">Warehouse
                <select name="warehouse_id" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                    <option value="">From PO</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <input name="supplier_invoice_number" placeholder="Supplier invoice" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            <input name="supplier_invoice_date" type="date" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            <input name="receipt_date" type="date" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
        </div>
        <div class="mt-6 rounded-lg border border-slate-200 dark:border-slate-800">
            <div class="border-b border-slate-200 px-4 py-3 text-sm font-semibold dark:border-slate-800">Received item</div>
            <div class="grid gap-4 p-4 md:grid-cols-6">
                <select name="items[0][product_id]" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                    <option value="">Product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
                <select name="items[0][stock_location_id]" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                    <option value="">Default location</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
                <input name="items[0][received_quantity]" type="number" min="0.001" step="0.001" placeholder="Received" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                <input name="items[0][accepted_quantity]" type="number" min="0" step="0.001" placeholder="Accepted" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                <input name="items[0][rejected_quantity]" type="number" min="0" step="0.001" placeholder="Rejected" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                <input name="items[0][unit_cost]" type="number" min="0" step="0.01" placeholder="Unit cost" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            </div>
        </div>
        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('purchases.grn.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Cancel</a>
            <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Create GRN</button>
        </div>
    </form>
@endsection

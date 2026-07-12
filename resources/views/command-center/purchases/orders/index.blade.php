@extends('layouts.admin')

@section('title', 'Purchase Orders')
@section('page-title', 'Purchase Orders')
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>Orders</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <form method="GET" class="flex flex-1 flex-col gap-2 sm:flex-row">
            <input name="search" value="{{ request('search') }}" placeholder="Search PO number" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            <select name="status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                <option value="">All statuses</option>
                @foreach (['draft', 'pending_approval', 'approved', 'sent', 'partially_received', 'received', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                @endforeach
            </select>
            <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Filter</button>
        </form>
        <a href="{{ route('purchases.orders.create') }}" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">New PO</a>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr><th class="px-5 py-3">PO</th><th class="px-5 py-3">Supplier</th><th class="px-5 py-3">Warehouse</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Total</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($orders as $order)
                        <tr>
                            <td class="px-5 py-3 font-medium"><a href="{{ route('purchases.orders.show', $order) }}">{{ $order->po_number }}</a></td>
                            <td class="px-5 py-3 text-slate-500">{{ $order->supplier?->name }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $order->warehouse?->name }}</td>
                            <td class="px-5 py-3">{{ str($order->status->value)->headline() }}</td>
                            <td class="px-5 py-3 text-right">₹{{ number_format((float) $order->grand_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No purchase orders found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $orders->links() }}</div>
    </section>
@endsection

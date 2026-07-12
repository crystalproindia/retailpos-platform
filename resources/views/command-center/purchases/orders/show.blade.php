@extends('layouts.admin')

@section('title', $order->po_number)
@section('page-title', $order->po_number)
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>Orders</span><span>/</span><span>{{ $order->po_number }}</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="text-sm text-slate-500 dark:text-slate-400">{{ $order->supplier?->name }} · {{ $order->warehouse?->name }} · {{ str($order->status->value)->headline() }}</div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('purchases.orders.print', $order) }}" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">Print</a>
            <form method="POST" action="{{ route('purchases.orders.submit', $order) }}">@csrf<button class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">Submit</button></form>
            <form method="POST" action="{{ route('purchases.orders.approve', $order) }}">@csrf<button class="rounded-md border border-teal-300 px-3 py-2 text-sm text-teal-700 dark:border-teal-800 dark:text-teal-200">Approve</button></form>
            <form method="POST" action="{{ route('purchases.orders.send', $order) }}">@csrf<button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Mark sent</button></form>
            <form method="POST" action="{{ route('purchases.orders.cancel', $order) }}">@csrf<button class="rounded-md border border-rose-300 px-3 py-2 text-sm text-rose-700 dark:border-rose-800 dark:text-rose-200">Cancel</button></form>
        </div>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr><th class="px-5 py-3">Product</th><th class="px-5 py-3 text-right">Ordered</th><th class="px-5 py-3 text-right">Received</th><th class="px-5 py-3 text-right">Pending</th><th class="px-5 py-3 text-right">Line total</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $item->product_name_snapshot }}</td>
                            <td class="px-5 py-3 text-right">{{ $item->ordered_quantity }}</td>
                            <td class="px-5 py-3 text-right">{{ $item->received_quantity }}</td>
                            <td class="px-5 py-3 text-right">{{ $item->pending_quantity }}</td>
                            <td class="px-5 py-3 text-right">₹{{ number_format((float) $item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 text-sm font-semibold dark:bg-slate-800">
                    <tr><td colspan="4" class="px-5 py-3 text-right">Grand total</td><td class="px-5 py-3 text-right">₹{{ number_format((float) $order->grand_total, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </section>
@endsection

@extends('layouts.admin')

@section('title', $purchaseRequest->request_number)
@section('page-title', $purchaseRequest->request_number)
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>Requests</span><span>/</span><span>{{ $purchaseRequest->request_number }}</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div class="text-sm text-slate-500 dark:text-slate-400">
            {{ str($purchaseRequest->status->value)->headline() }} · {{ str($purchaseRequest->priority->value)->headline() }} · {{ $purchaseRequest->warehouse?->name ?: 'Any warehouse' }}
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('purchases.requests.submit', $purchaseRequest) }}">@csrf<button class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">Submit</button></form>
            <form method="POST" action="{{ route('purchases.requests.approve', $purchaseRequest) }}">@csrf<button class="rounded-md border border-teal-300 px-3 py-2 text-sm text-teal-700 dark:border-teal-800 dark:text-teal-200">Approve</button></form>
            <form method="POST" action="{{ route('purchases.requests.reject', $purchaseRequest) }}">@csrf<button class="rounded-md border border-rose-300 px-3 py-2 text-sm text-rose-700 dark:border-rose-800 dark:text-rose-200">Reject</button></form>
            <form method="POST" action="{{ route('purchases.requests.convert', $purchaseRequest) }}">@csrf<button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Convert to PO</button></form>
        </div>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr><th class="px-5 py-3">Product</th><th class="px-5 py-3">Supplier</th><th class="px-5 py-3 text-right">Requested</th><th class="px-5 py-3 text-right">Approved</th><th class="px-5 py-3 text-right">Estimate</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($purchaseRequest->items as $item)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $item->product?->name }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $item->supplier?->name ?: 'Not selected' }}</td>
                            <td class="px-5 py-3 text-right">{{ $item->requested_quantity }}</td>
                            <td class="px-5 py-3 text-right">{{ $item->approved_quantity ?: '-' }}</td>
                            <td class="px-5 py-3 text-right">₹{{ number_format((float) $item->estimated_price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

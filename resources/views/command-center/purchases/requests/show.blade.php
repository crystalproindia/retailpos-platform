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
            @if ($purchaseRequest->status->value === 'draft')
                <form method="POST" action="{{ route('purchases.requests.submit', $purchaseRequest) }}">@csrf<button class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">Submit for approval</button></form>
            @endif
            <form method="POST" action="{{ route('purchases.requests.duplicate', $purchaseRequest) }}">@csrf<button class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">Duplicate</button></form>
            @if (in_array($purchaseRequest->status->value, ['approved', 'partially_approved']))
                <form method="POST" action="{{ route('purchases.requests.convert', $purchaseRequest) }}">@csrf<button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Convert remaining to PO</button></form>
            @endif
        </div>
    </div>

    @if ($purchaseRequest->status->value === 'pending_review')
        <form method="POST" action="{{ route('purchases.requests.approve', $purchaseRequest) }}" class="mb-5 rounded-lg border border-teal-200 bg-teal-50 p-4 dark:border-teal-900/70 dark:bg-teal-950/20">
            @csrf
            <div class="mb-3"><h2 class="font-semibold">Approval review</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Adjust only the quantities that should be ordered. Stock is not changed at this stage.</p></div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($purchaseRequest->items as $item)
                    <label class="rounded-md border border-teal-100 bg-white p-3 text-sm dark:border-teal-900 dark:bg-slate-900">
                        <input type="hidden" name="items[{{ $loop->index }}][item_id]" value="{{ $item->id }}">
                        <span class="block font-medium">{{ $item->product?->name }}</span>
                        <span class="mt-1 block text-xs text-slate-500">Requested: {{ $item->requested_quantity }}</span>
                        <input name="items[{{ $loop->index }}][approved_quantity]" type="number" min="0" max="{{ $item->requested_quantity }}" step="0.001" value="{{ $item->requested_quantity }}" class="mt-2 w-full rounded-md border-slate-300 text-base dark:border-slate-700 dark:bg-slate-950">
                        <input name="items[{{ $loop->index }}][approval_notes]" placeholder="Optional note" class="mt-2 w-full rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                    </label>
                @endforeach
            </div>
            <div class="mt-4 flex flex-wrap justify-end gap-2"><button class="rounded-md bg-teal-700 px-4 py-2 text-sm font-medium text-white">Approve quantities</button></div>
        </form>
        <form method="POST" action="{{ route('purchases.requests.reject', $purchaseRequest) }}" class="mb-5 flex flex-col gap-2 rounded-lg border border-rose-200 bg-rose-50 p-4 sm:flex-row dark:border-rose-900/70 dark:bg-rose-950/20">
            @csrf
            <label class="sr-only" for="rejection-comments">Reason for rejection</label>
            <input id="rejection-comments" name="comments" required placeholder="Reason for rejection" class="min-w-0 flex-1 rounded-md border-rose-200 bg-white dark:border-rose-900 dark:bg-slate-900">
            <button class="rounded-md border border-rose-300 px-4 py-2 text-sm font-medium text-rose-700 dark:border-rose-800 dark:text-rose-200">Reject request</button>
        </form>
    @endif

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

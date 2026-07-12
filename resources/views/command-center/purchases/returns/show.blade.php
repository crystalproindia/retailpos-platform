@extends('layouts.admin')

@section('title', $return->return_number)
@section('page-title', $return->return_number)
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>Returns</span><span>/</span><span>{{ $return->return_number }}</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $return->supplier?->name }} · {{ $return->warehouse?->name }} · {{ str($return->status->value)->headline() }}</p>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('purchases.returns.approve', $return) }}">@csrf<button class="rounded-md border border-teal-300 px-3 py-2 text-sm text-teal-700 dark:border-teal-800 dark:text-teal-200">Approve</button></form>
            <form method="POST" action="{{ route('purchases.returns.complete', $return) }}">@csrf<button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Complete</button></form>
        </div>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr><th class="px-5 py-3">Product</th><th class="px-5 py-3">Location</th><th class="px-5 py-3 text-right">Quantity</th><th class="px-5 py-3 text-right">Unit cost</th><th class="px-5 py-3">Reason</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($return->items as $item)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $item->product?->name }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $item->stockLocation?->name ?: 'Default' }}</td>
                            <td class="px-5 py-3 text-right">{{ $item->quantity }}</td>
                            <td class="px-5 py-3 text-right">₹{{ number_format((float) $item->unit_cost, 2) }}</td>
                            <td class="px-5 py-3">{{ $item->reason }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

@extends('layouts.admin')

@section('title', $receipt->grn_number)
@section('page-title', $receipt->grn_number)
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>GRN</span><span>/</span><span>{{ $receipt->grn_number }}</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <div class="mb-5 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $receipt->supplier?->name }} · {{ $receipt->warehouse?->name }} · {{ str($receipt->status->value)->headline() }}</p>
        <form method="POST" action="{{ route('purchases.grn.receive', $receipt) }}">@csrf<button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Post to stock</button></form>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr><th class="px-5 py-3">Product</th><th class="px-5 py-3 text-right">Received</th><th class="px-5 py-3 text-right">Accepted</th><th class="px-5 py-3 text-right">Rejected</th><th class="px-5 py-3 text-right">Unit cost</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($receipt->items as $item)
                        <tr>
                            <td class="px-5 py-3 font-medium">{{ $item->product?->name }}</td>
                            <td class="px-5 py-3 text-right">{{ $item->received_quantity }}</td>
                            <td class="px-5 py-3 text-right">{{ $item->accepted_quantity }}</td>
                            <td class="px-5 py-3 text-right">{{ $item->rejected_quantity }}</td>
                            <td class="px-5 py-3 text-right">₹{{ number_format((float) $item->unit_cost, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection

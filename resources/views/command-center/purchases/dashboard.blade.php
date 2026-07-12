@extends('layouts.admin')

@section('title', 'Purchases')
@section('page-title', 'Purchases')
@section('breadcrumbs')
    <span>/</span><span>Purchases</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach ($dashboard['cards'] as $card)
            @php
                $tone = match ($card['tone']) {
                    'success' => 'border-teal-200 bg-teal-50 text-teal-900 dark:border-teal-900 dark:bg-teal-950 dark:text-teal-100',
                    'warning' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100',
                    'danger' => 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100',
                    'info' => 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100',
                    default => 'border-slate-200 bg-white text-slate-950 dark:border-slate-800 dark:bg-slate-900 dark:text-white',
                };
            @endphp
            <section class="rounded-lg border p-4 shadow-sm {{ $tone }}">
                <p class="text-xs font-medium uppercase tracking-wide opacity-70">{{ $card['label'] }}</p>
                <p class="mt-2 text-2xl font-semibold">{{ $card['value'] }}</p>
            </section>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Purchase value</p>
            <p class="mt-2 text-3xl font-semibold text-slate-950 dark:text-white">₹{{ number_format($dashboard['purchaseValue'], 2) }}</p>
            <div class="mt-5 space-y-3">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Pending approvals</h2>
                @forelse ($dashboard['pendingApprovals']['requests'] as $purchaseRequest)
                    <a href="{{ route('purchases.requests.show', $purchaseRequest) }}" class="block rounded-lg bg-slate-50 p-3 text-sm hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700">
                        <span class="font-medium">{{ $purchaseRequest->request_number }}</span>
                        <span class="text-slate-500 dark:text-slate-400">requested by {{ $purchaseRequest->requester?->name }}</span>
                    </a>
                @empty
                    <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500 dark:bg-slate-800 dark:text-slate-400">No purchase requests waiting for approval.</p>
                @endforelse
                @foreach ($dashboard['pendingApprovals']['orders'] as $order)
                    <a href="{{ route('purchases.orders.show', $order) }}" class="block rounded-lg bg-amber-50 p-3 text-sm text-amber-900 hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-100">
                        <span class="font-medium">{{ $order->po_number }}</span>
                        <span>from {{ $order->supplier?->name }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Recent purchase orders</h2>
                <a href="{{ route('purchases.orders.create') }}" class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">New PO</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3">PO</th>
                            <th class="px-5 py-3">Supplier</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($dashboard['recentOrders'] as $order)
                            <tr>
                                <td class="px-5 py-3 font-medium"><a href="{{ route('purchases.orders.show', $order) }}">{{ $order->po_number }}</a></td>
                                <td class="px-5 py-3 text-slate-500 dark:text-slate-400">{{ $order->supplier?->name }}</td>
                                <td class="px-5 py-3">{{ str($order->status->value)->headline() }}</td>
                                <td class="px-5 py-3 text-right">₹{{ number_format((float) $order->grand_total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No purchase orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

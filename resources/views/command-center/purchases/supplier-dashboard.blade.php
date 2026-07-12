@extends('layouts.admin')

@section('title', 'Supplier Dashboard')
@section('page-title', 'Supplier Dashboard')
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>Supplier Dashboard</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($dashboard['cards'] as $card)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-white">{{ $card['value'] }}</p>
            </section>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Top suppliers</h2>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($dashboard['topSuppliers'] as $supplier)
                    <a href="{{ route('purchases.suppliers.show', $supplier) }}" class="flex items-center justify-between px-5 py-4 text-sm hover:bg-slate-50 dark:hover:bg-slate-800">
                        <span>
                            <span class="block font-medium text-slate-950 dark:text-white">{{ $supplier->name }}</span>
                            <span class="text-slate-500 dark:text-slate-400">{{ $supplier->products_count }} products · {{ $supplier->purchase_orders_count }} POs</span>
                        </span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 font-medium dark:bg-slate-800">{{ $supplier->rating ? number_format((float) $supplier->rating, 1) : 'No score' }}</span>
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-slate-500">No suppliers yet.</p>
                @endforelse
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Recent receipts and returns</h2>
            </div>
            <div class="grid gap-4 p-5 md:grid-cols-2">
                <div>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Receipts</h3>
                    <div class="mt-3 space-y-2">
                        @forelse ($dashboard['recentReceipts'] as $receipt)
                            <a href="{{ route('purchases.grn.show', $receipt) }}" class="block rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800">{{ $receipt->grn_number }} · {{ $receipt->supplier?->name }}</a>
                        @empty
                            <p class="text-sm text-slate-500">No receipts yet.</p>
                        @endforelse
                    </div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Returns</h3>
                    <div class="mt-3 space-y-2">
                        @forelse ($dashboard['recentReturns'] as $return)
                            <a href="{{ route('purchases.returns.show', $return) }}" class="block rounded-lg bg-slate-50 p-3 text-sm dark:bg-slate-800">{{ $return->return_number }} · {{ $return->supplier?->name }}</a>
                        @empty
                            <p class="text-sm text-slate-500">No returns yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    </div>
@endsection

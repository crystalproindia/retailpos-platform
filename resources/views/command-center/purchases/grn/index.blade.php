@extends('layouts.admin')

@section('title', 'Goods Receipts')
@section('page-title', 'Goods Receipts')
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>GRN</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <div class="mb-4 flex justify-end">
        <a href="{{ route('purchases.grn.create') }}" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">New GRN</a>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr><th class="px-5 py-3">GRN</th><th class="px-5 py-3">Supplier</th><th class="px-5 py-3">Warehouse</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Date</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($receipts as $receipt)
                        <tr>
                            <td class="px-5 py-3 font-medium"><a href="{{ route('purchases.grn.show', $receipt) }}">{{ $receipt->grn_number }}</a></td>
                            <td class="px-5 py-3 text-slate-500">{{ $receipt->supplier?->name }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $receipt->warehouse?->name }}</td>
                            <td class="px-5 py-3">{{ str($receipt->status->value)->headline() }}</td>
                            <td class="px-5 py-3">{{ $receipt->receipt_date?->format('d M Y') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No goods receipts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $receipts->links() }}</div>
    </section>
@endsection

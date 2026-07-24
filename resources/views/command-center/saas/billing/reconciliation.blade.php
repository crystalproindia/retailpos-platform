@extends('layouts.admin')
@section('title','SaaS Billing Reconciliation')
@section('page-title','SaaS Billing Reconciliation')
@section('breadcrumbs')<span>/</span><a href="{{ route('saas.billing.index') }}">Billing</a><span>/</span><span>Reconciliation</span>@endsection
@section('content')
@include('command-center.saas.partials.nav')
<section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
        <h2 class="font-semibold text-slate-950 dark:text-white">Reconciliation</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Unreconciled subscription payments that need finance review.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800">
                <tr><th class="px-5 py-3">Payment</th><th class="px-5 py-3">Tenant</th><th class="px-5 py-3">Invoice</th><th class="px-5 py-3">Reference</th><th class="px-5 py-3">Amount</th><th class="px-5 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($payments as $payment)
                    <tr>
                        <td class="px-5 py-4 font-medium">{{ $payment->receipt_number ?? $payment->payment_number }}</td>
                        <td class="px-5 py-4">{{ $payment->company?->name }}</td>
                        <td class="px-5 py-4"><a href="{{ route('saas.billing.show', $payment->invoice) }}" class="font-semibold text-teal-700 dark:text-teal-300">{{ $payment->invoice?->invoice_number }}</a></td>
                        <td class="px-5 py-4 text-slate-500">{{ $payment->transaction_reference ?? 'Manual review' }}</td>
                        <td class="px-5 py-4">{{ $payment->currency }} {{ number_format((float)$payment->amount, 2) }}</td>
                        <td class="px-5 py-4"><x-status-badge :status="$payment->reconciliation_status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No unreconciled payments are waiting for review.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
<div class="mt-4">{{ $payments->links() }}</div>
@endsection

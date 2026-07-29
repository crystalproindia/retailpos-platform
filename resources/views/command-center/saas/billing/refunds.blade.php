@extends('layouts.admin')
@section('title','SaaS Billing Refunds')
@section('page-title','SaaS Billing Refunds')
@section('breadcrumbs')<span>/</span><a href="{{ route('saas.billing.index') }}">Billing</a><span>/</span><span>Refunds</span>@endsection
@section('content')
@include('command-center.saas.partials.nav')
<section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
        <h2 class="font-semibold text-slate-950 dark:text-white">Refunds</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Refund requests and processed subscription billing credits.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800">
                <tr><th class="px-5 py-3">Refund</th><th class="px-5 py-3">Tenant</th><th class="px-5 py-3">Invoice</th><th class="px-5 py-3">Payment</th><th class="px-5 py-3">Amount</th><th class="px-5 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($refunds as $refund)
                    <tr>
                        <td class="px-5 py-4 font-medium">{{ $refund->refund_number }}</td>
                        <td class="px-5 py-4">{{ $refund->company?->name }}</td>
                        <td class="px-5 py-4"><a href="{{ route('saas.billing.show', $refund->invoice) }}" class="font-semibold text-teal-700 dark:text-teal-300">{{ $refund->invoice?->invoice_number }}</a></td>
                        <td class="px-5 py-4 text-slate-500">{{ $refund->payment?->receipt_number ?? $refund->payment?->payment_number }}</td>
                        <td class="px-5 py-4">{{ $refund->currency }} {{ number_format((float)$refund->amount, 2) }}</td>
                        <td class="px-5 py-4"><x-status-badge :status="$refund->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-5 py-12 text-center text-sm text-slate-500">No refund requests have been recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
<div class="mt-4">{{ $refunds->links() }}</div>
@endsection

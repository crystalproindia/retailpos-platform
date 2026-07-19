@extends('layouts.admin')

@section('title', $invoice->invoice_number)
@section('page-title', $invoice->invoice_number)

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-sm font-semibold text-teal-700 dark:text-teal-300">{{ $invoice->invoice_number }}</p>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $invoice->isOverdue() ? 'Overdue' : $invoice->status?->label() }}</span>
                    </div>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $invoice->billing_company ?: $invoice->billing_name }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Due {{ $invoice->due_date?->format('d M Y') ?? 'not set' }} · {{ $invoice->currency }} {{ number_format((float) $invoice->balance_due, 2) }} outstanding</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @if ($invoice->status?->isEditable())
                        @can('sales.invoices.update')
                            <a href="{{ route('sales.invoices.edit', $invoice) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Edit draft</a>
                        @endcan
                        <form method="POST" action="{{ route('sales.invoices.issue', $invoice) }}">@csrf<button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Issue invoice</button></form>
                    @endif
                    <a href="{{ route('sales.invoices.pdf', $invoice) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Download PDF</a>
                    @if (! $invoice->status?->isEditable() && $invoice->billing_email)
                        <form method="POST" action="{{ route('sales.invoices.send', $invoice) }}">@csrf<input type="hidden" name="email" value="{{ $invoice->billing_email }}"><button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Send email</button></form>
                        <form method="POST" action="{{ route('sales.invoices.reminder', $invoice) }}">@csrf<input type="hidden" name="email" value="{{ $invoice->billing_email }}"><button class="rounded-lg border border-amber-200 px-4 py-2 text-sm font-semibold text-amber-800 dark:border-amber-900 dark:text-amber-200">Send reminder</button></form>
                    @endif
                    <a href="{{ route('sales.invoices.whatsapp', $invoice) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">WhatsApp</a>
                    @if ($invoice->public_token_hash && ! $invoice->public_token_revoked_at)
                        <form method="POST" action="{{ route('sales.invoices.public-link.revoke', $invoice) }}" onsubmit="return confirm('Revoke the current secure invoice link?')">@csrf<button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Revoke link</button></form>
                    @endif
                    @if ($invoice->amount_paid <= 0 && ! $invoice->status?->isTerminal())
                        <form method="POST" action="{{ route('sales.invoices.cancel', $invoice) }}" onsubmit="return confirm('Cancel this invoice?')">@csrf<button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 dark:border-rose-900 dark:text-rose-300">Cancel</button></form>
                    @endif
                </div>
            </div>
            @if (session('whatsappMessage'))
                <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100">
                    <p class="font-semibold">Copy the prepared WhatsApp message</p>
                    <p class="mt-2 whitespace-pre-line">{{ session('whatsappMessage') }}</p>
                </div>
            @endif
        </section>

        <section class="grid gap-6 xl:grid-cols-[minmax(0,1.35fr)_360px]">
            <article class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="font-semibold text-slate-950 dark:text-white">Items and totals</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400"><tr><th class="p-4">Item</th><th class="p-4 text-right">Qty</th><th class="p-4 text-right">Rate</th><th class="p-4 text-right">Discount</th><th class="p-4 text-right">Tax</th><th class="p-4 text-right">Total</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($invoice->items as $item)
                                <tr><td class="p-4"><p class="font-medium text-slate-950 dark:text-white">{{ $item->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $item->description }}</p></td><td class="p-4 text-right">{{ $item->quantity }} {{ $item->unit }}</td><td class="p-4 text-right">{{ number_format((float) $item->unit_price, 2) }}</td><td class="p-4 text-right">{{ number_format((float) $item->discount_amount, 2) }}</td><td class="p-4 text-right">{{ number_format((float) $item->tax_amount, 2) }}</td><td class="p-4 text-right font-semibold">{{ number_format((float) $item->line_total, 2) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="ml-auto max-w-sm space-y-2 p-5 text-sm">
                    <div class="flex justify-between"><span>Subtotal</span><span>{{ $invoice->currency }} {{ number_format((float) $invoice->subtotal, 2) }}</span></div>
                    <div class="flex justify-between"><span>Discount</span><span>{{ $invoice->currency }} {{ number_format((float) $invoice->discount_total, 2) }}</span></div>
                    <div class="flex justify-between"><span>Tax</span><span>{{ $invoice->currency }} {{ number_format((float) $invoice->tax_total, 2) }}</span></div>
                    <div class="flex justify-between"><span>Adjustment</span><span>{{ $invoice->currency }} {{ number_format((float) $invoice->adjustment_total, 2) }}</span></div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold dark:border-slate-700"><span>Total</span><span>{{ $invoice->currency }} {{ number_format((float) $invoice->grand_total, 2) }}</span></div>
                    <div class="flex justify-between text-teal-700 dark:text-teal-300"><span>Paid</span><span>{{ $invoice->currency }} {{ number_format((float) $invoice->amount_paid, 2) }}</span></div>
                    <div class="flex justify-between font-semibold text-rose-700 dark:text-rose-300"><span>Balance</span><span>{{ $invoice->currency }} {{ number_format((float) $invoice->balance_due, 2) }}</span></div>
                </div>
            </article>

            <aside class="space-y-6">
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-semibold text-slate-950 dark:text-white">Record payment</h2>
                    @if (! $invoice->status?->isEditable() && ! $invoice->status?->isTerminal() && $invoice->balance_due > 0)
                        <form method="POST" action="{{ route('sales.invoices.payments.store', $invoice) }}" class="mt-4 space-y-3">@csrf
                            <input name="amount" type="number" step="0.01" min="0.01" max="{{ $invoice->balance_due }}" required placeholder="Amount" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <input name="currency" value="{{ $invoice->currency }}" readonly class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <input name="payment_date" type="date" value="{{ today()->toDateString() }}" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <select name="payment_method" class="w-full rounded-lg border border-slate-300 px-3 py-2">@foreach (['bank_transfer','cash','cheque','card','upi','online','other'] as $method)<option value="{{ $method }}">{{ str($method)->replace('_', ' ')->headline() }}</option>@endforeach</select>
                            <input name="transaction_reference" placeholder="Transaction reference" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Record payment</button>
                        </form>
                    @else
                        <p class="mt-3 text-sm text-slate-500">Payments can be recorded after issue while a balance remains.</p>
                    @endif
                </article>

                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="font-semibold text-slate-950 dark:text-white">Payment receipts</h2>
                    <div class="mt-4 space-y-3">
                        @forelse ($invoice->payments as $payment)
                            <div class="rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-800">
                                <div class="flex justify-between gap-3"><span class="font-semibold">{{ $payment->receipt_number }}</span><span>{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</span></div>
                                <p class="mt-1 text-xs text-slate-500">{{ str($payment->payment_method)->replace('_', ' ')->headline() }} · {{ $payment->payment_date?->format('d M Y') }} · {{ $payment->status?->label() }}</p>
                                <div class="mt-3 flex flex-wrap gap-3 text-xs font-semibold"><a href="{{ route('sales.invoices.receipts.pdf', [$invoice, $payment]) }}" class="text-teal-700 dark:text-teal-300">Receipt PDF</a>@if ($invoice->billing_email)<form method="POST" action="{{ route('sales.invoices.receipts.send', [$invoice, $payment]) }}">@csrf<button class="text-teal-700 dark:text-teal-300">Email receipt</button></form>@endif<a href="{{ route('sales.invoices.receipts.whatsapp', [$invoice, $payment]) }}" class="text-teal-700 dark:text-teal-300">WhatsApp receipt</a></div>
                                @if ($payment->status?->value === 'pending')<form method="POST" action="{{ route('sales.invoices.payments.clear', [$invoice, $payment]) }}" class="mt-3">@csrf<button class="text-xs font-semibold text-teal-700 dark:text-teal-300">Mark payment cleared</button></form>@endif
                                @if ($payment->status?->value !== 'reversed')
                                    <form method="POST" action="{{ route('sales.invoices.payments.reverse', [$invoice, $payment]) }}" class="mt-3">@csrf<input name="reason" required placeholder="Reason to reverse payment" class="w-full rounded border border-slate-300 px-2 py-1.5 text-xs"><button class="mt-2 text-xs font-semibold text-rose-700 dark:text-rose-300">Reverse payment</button></form>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No payments recorded.</p>
                        @endforelse
                    </div>
                </article>
            </aside>
        </section>
    </div>
@endsection

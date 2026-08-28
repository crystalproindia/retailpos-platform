@extends('layouts.admin')

@section('title', $invoice->invoice_number)
@section('page-title', $invoice->invoice_number)

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')
        @php($latestInvoiceDelivery = $invoice->latestInvoiceEmailDelivery)

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <p class="text-sm font-semibold text-teal-700 dark:text-teal-300">{{ $invoice->invoice_number }}</p>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $invoice->isOverdue() ? 'Overdue' : $invoice->status?->label() }}</span>
                        @if((int) $invoice->amendment_version > 1)<span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-800 dark:bg-violet-950 dark:text-violet-200">Amended · Version {{ $invoice->amendment_version }}</span>@endif
                    </div>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $invoice->billing_company ?: $invoice->billing_name }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Due {{ $invoice->due_date?->format('d M Y') ?? 'not set' }} · {{ $invoice->currency }} {{ number_format((float) $invoice->balance_due, 2) }} outstanding</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('tasks.create_work')
                        <a href="{{ route('tasks.index', ['create_related_type' => 'invoice', 'create_related_id' => $invoice->id]) }}#quick-add" class="rounded-lg border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-800 hover:bg-teal-50 dark:border-teal-800 dark:text-teal-200">Add task</a>
                    @endcan
                    @if ($invoice->status?->isEditable())
                        @can('sales.invoices.update')
                            <a href="{{ route('sales.invoices.edit', $invoice) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Edit draft</a>
                        @endcan
                        @php($creditLimitExceeded = $invoice->customer?->credit_limit !== null && (($financeSummary['net_exposure'] / 100) + (float) $invoice->grand_total) > (float) $invoice->customer->credit_limit)
                        <form method="POST" action="{{ route('sales.invoices.issue', $invoice) }}" class="contents">@csrf
                            @if($creditLimitExceeded)
                                @can('finance.credit-limits.override')
                                    <input type="hidden" name="credit_limit_override" value="1"><input name="credit_limit_override_reason" required minlength="5" aria-label="Credit limit override reason" placeholder="Override reason" class="w-44 rounded-lg border-amber-300 text-sm dark:border-amber-800 dark:bg-slate-950">
                                @endcan
                            @endif
                            <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Issue invoice</button>
                        </form>
                    @endif
                    @can('sales.invoices.amend')
                        @if(in_array($invoice->status?->value, ['issued', 'sent', 'viewed', 'partially_paid', 'paid', 'overdue'], true) && $invoice->return_status !== 'full')
                            <a href="{{ route('sales.invoices.amendments.create', $invoice) }}" class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500 dark:bg-violet-400 dark:text-slate-950 dark:hover:bg-violet-300">Amend invoice</a>
                        @endif
                    @endcan
                    <a href="{{ route('sales.invoices.print', $invoice) }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Print</a>
                    <a href="{{ route('sales.invoices.pdf', $invoice) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Download PDF</a>
                    @can('sales.returns.create')
                        @if(!in_array($invoice->status?->value, ['draft', 'cancelled', 'void'], true) && $invoice->return_status !== 'full')
                            <a href="{{ route('sales.invoices.returns.create', $invoice) }}" class="rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-200 dark:hover:bg-amber-950/30">Create Return / Credit Note</a>
                        @endif
                    @endcan
                    @if (! $invoice->status?->isEditable() && $invoice->billing_email)
                        @if ($latestInvoiceDelivery && in_array($latestInvoiceDelivery->status, ['temporarily_failed', 'permanently_failed'], true))
                            <form method="POST" action="{{ route('sales.invoices.email-deliveries.resend', [$invoice, $latestInvoiceDelivery]) }}">@csrf<button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Resend invoice email</button></form>
                        @else
                            <form method="POST" action="{{ route('sales.invoices.send', $invoice) }}">@csrf<input type="hidden" name="email" value="{{ $invoice->billing_email }}"><button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Send email</button></form>
                        @endif
                        @can('sales.reminders.send')
                            @if ($invoice->balance_due > 0 && ! $invoice->status?->isTerminal() && $reminderRules->isNotEmpty())
                                <button type="button" data-invoice-reminder-open class="rounded-lg border border-amber-200 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-50 dark:border-amber-900 dark:text-amber-200 dark:hover:bg-amber-950/30">Send payment reminder</button>
                            @endif
                        @endcan
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
            @if ($latestInvoiceDelivery)
                @php($delivery = $latestInvoiceDelivery)
                <div @class([
                    'mt-4 rounded-lg border p-4 text-sm',
                    'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-100' => in_array($delivery->status, ['temporarily_failed', 'permanently_failed', 'bounced', 'rejected'], true),
                    'border-teal-200 bg-teal-50 text-teal-900 dark:border-teal-900 dark:bg-teal-950/30 dark:text-teal-100' => in_array($delivery->status, ['sent', 'delivered'], true),
                    'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-100' => ! in_array($delivery->status, ['temporarily_failed', 'permanently_failed', 'bounced', 'rejected', 'sent', 'delivered'], true),
                ])>
                    <p class="font-semibold">Email status: {{ str($delivery->status)->replace('_', ' ')->headline() }}</p>
                    @if (in_array($delivery->status, ['temporarily_failed', 'permanently_failed'], true))
                        <p class="font-semibold">The latest invoice email could not be delivered.</p>
                        <p class="mt-1">{{ $delivery->failure_reason ?: 'The delivery service could not complete the email.' }}</p>
                    @elseif ($delivery->status === 'delivered')
                        <p class="mt-1">The provider confirmed delivery of the invoice email and its PDF attachment.</p>
                    @elseif ($delivery->status === 'sent')
                        <p class="mt-1">SMTP accepted the invoice email with its PDF attachment. Delivery confirmation will appear when a trusted provider event is available.</p>
                    @else
                        <p class="mt-1">The latest invoice email is queued for delivery with its PDF attachment.</p>
                    @endif
                    <p class="mt-2 text-xs opacity-80">Last attempt: {{ ($delivery->sent_at ?: $delivery->failed_at ?: $delivery->queued_at)?->format('d M Y H:i') ?? 'Not attempted yet' }} · Retries: {{ max(0, $delivery->attempt_count - 1) }}</p>
                </div>
            @endif
        </section>

        @if($invoice->status?->isEditable() && $creditLimitExceeded)
            <section class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-100"><p class="font-semibold">Credit limit review required</p><p class="mt-1">Limit: {{ $invoice->currency }} {{ number_format((float)$invoice->customer->credit_limit,2) }} · Current net exposure: {{ $invoice->currency }} {{ number_format($financeSummary['net_exposure']/100,2) }} · Projected: {{ $invoice->currency }} {{ number_format(($financeSummary['net_exposure']/100)+(float)$invoice->grand_total,2) }}.</p>@cannot('finance.credit-limits.override')<p class="mt-2">Ask an authorized manager to review this invoice.</p>@endcannot @error('credit_limit')<p class="mt-2 font-semibold">{{ $message }}</p>@enderror @error('credit_limit_override_reason')<p class="mt-2 font-semibold">{{ $message }}</p>@enderror</section>
        @endif

        @if($invoice->amendments->isNotEmpty())
            <section class="overflow-hidden rounded-lg border border-violet-200 bg-white shadow-sm dark:border-violet-900 dark:bg-slate-900">
                <div class="border-b border-violet-100 bg-violet-50/70 p-5 dark:border-violet-900 dark:bg-violet-950/25"><h2 class="font-semibold text-slate-950 dark:text-white">Amendment History</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Confirmed additions are permanent. Corrections to original lines use a return or credit note.</p></div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($invoice->amendments as $amendment)
                        <article class="p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"><div><p class="font-semibold text-slate-950 dark:text-white">Version {{ $amendment->version_to }} · {{ $amendment->finalized_at->format('d M Y, H:i') }}</p><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $amendment->reason }}</p><p class="mt-1 text-xs text-slate-500">{{ $amendment->amendment_type === 'overall_discount' ? 'Overall invoice discount · '.str($amendment->discount_type)->headline().' '.$amendment->discount_value : 'Added products or services' }} · Confirmed by {{ $amendment->creator?->name ?? 'System' }}</p></div><div class="text-left sm:text-right"><p class="text-sm font-semibold text-violet-700 dark:text-violet-300">{{ (float) $amendment->amount_added < 0 ? '−' : '+' }} {{ $invoice->currency }} {{ number_format(abs((float) $amendment->amount_added), 2) }}</p><p class="mt-1 text-xs text-slate-500">Updated total {{ $invoice->currency }} {{ number_format((float) $amendment->amount_after, 2) }}</p></div></div>
                            <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach($amendment->items as $item)<div class="rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-800"><p class="font-medium text-slate-950 dark:text-white">{{ $item->name_snapshot }}</p><p class="mt-1 text-xs text-slate-500">{{ $item->quantity_snapshot }} × {{ $invoice->currency }} {{ number_format((float) $item->unit_price_snapshot, 2) }} · Tax {{ $invoice->currency }} {{ number_format((float) $item->tax_snapshot, 2) }}</p></div>@endforeach</div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-5 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-semibold text-slate-950 dark:text-white">Returns &amp; Credit Notes</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Finalized credits preserve the original invoice and do not automatically create a cash refund.</p></div>@if((float) $invoice->credited_total > 0)<span class="w-fit rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-200">{{ $invoice->currency }} {{ number_format((float) $invoice->credited_total, 2) }} credited</span>@endif</div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">@forelse($invoice->returns as $credit)<a href="{{ route('sales.credit-notes.show', $credit) }}" class="grid gap-2 p-5 text-sm transition hover:bg-slate-50 dark:hover:bg-slate-800/50 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center"><div><p class="font-semibold text-teal-700 dark:text-teal-300">{{ $credit->credit_note_number }}</p><p class="mt-1 text-xs text-slate-500">{{ $credit->issue_date->format('d M Y') }} · {{ $credit->items->sum('return_quantity') }} units · by {{ $credit->creator?->name ?? 'System' }}</p></div><span class="w-fit rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">Finalized</span><span class="font-semibold text-slate-950 dark:text-white">{{ $credit->currency }} {{ number_format((float) $credit->credit_total, 2) }}</span></a>@empty<div class="p-8 text-sm text-slate-500 dark:text-slate-400">No returns or credit notes have been recorded.</div>@endforelse</div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="font-semibold text-slate-950 dark:text-white">Invoice email delivery history</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Safe delivery progress for this invoice. A sent email is not treated as delivered until a trusted provider event confirms it.</p></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($invoice->invoiceEmailDeliveries as $delivery)
                    <div class="grid gap-3 p-5 text-sm md:grid-cols-[minmax(0,1.4fr)_auto_auto] md:items-center">
                        <div><p class="font-medium text-slate-950 dark:text-white">{{ $delivery->recipient }}</p><p class="mt-1 text-xs text-slate-500">{{ $delivery->created_at->format('d M Y H:i') }} · {{ max(0, $delivery->attempt_count - 1) }} retries @if($delivery->maskedProviderMessageId()) · Provider reference {{ $delivery->maskedProviderMessageId() }} @endif</p>@if($delivery->failure_reason)<p class="mt-1 text-xs text-rose-700 dark:text-rose-300">{{ $delivery->failure_reason }}</p>@endif</div>
                        <span class="w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ str($delivery->status)->replace('_', ' ')->headline() }}</span>
                        <span class="text-xs text-slate-500">{{ ($delivery->delivered_at ?: $delivery->sent_at ?: $delivery->failed_at ?: $delivery->queued_at)?->format('d M Y H:i') ?? 'Queued' }}</span>
                    </div>
                @empty
                    <div class="p-8 text-sm text-slate-500">No invoice email has been sent yet.</div>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="font-semibold text-slate-950 dark:text-white">Payment reminder activity</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Automatic and manual reminders are separate from the original invoice email. SMTP acceptance is not provider-confirmed delivery.</p></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($invoice->reminderEmailDeliveries as $delivery)
                    <div class="grid gap-3 p-5 text-sm lg:grid-cols-[minmax(0,1.4fr)_auto_auto] lg:items-center"><div><div class="flex flex-wrap items-center gap-2"><p class="font-medium text-slate-950 dark:text-white">{{ str($delivery->reminder_stage)->replace('_', ' ')->headline() }}</p><span class="rounded-full bg-slate-100 px-2 py-0.5 text-[0.65rem] font-semibold uppercase text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $delivery->reminder_source }}</span>@if(($delivery->payload['attachment_type'] ?? null) === \App\Services\Crm\InvoiceEmailAttachmentService::TYPE)<span class="text-xs text-slate-500">PDF attached</span>@endif</div><p class="mt-1 text-xs text-slate-500">{{ $delivery->recipient }} · {{ $delivery->queued_at?->format('d M Y H:i') ?? $delivery->created_at->format('d M Y H:i') }} · {{ max(0, $delivery->attempt_count - 1) }} retries @if($delivery->reminder_source === 'manual' && $delivery->createdBy) · Manual by {{ $delivery->createdBy->name }} @endif</p>@if($delivery->failure_reason)<p class="mt-1 text-xs text-rose-700 dark:text-rose-300">{{ $delivery->failure_reason }}</p>@endif</div><span class="w-fit rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ str($delivery->status)->replace('_', ' ')->headline() }}</span><span class="text-xs text-slate-500">{{ ($delivery->delivered_at ?: $delivery->sent_at ?: $delivery->failed_at ?: $delivery->queued_at)?->format('d M Y H:i') ?? 'Queued' }}</span></div>
                @empty
                    <div class="p-8 text-sm text-slate-500 dark:text-slate-400">No payment reminders have been queued for this invoice.</div>
                @endforelse
            </div>
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
                    @if((float) $invoice->credited_total > 0)<div class="flex justify-between text-amber-700 dark:text-amber-300"><span>Credit notes</span><span>{{ $invoice->currency }} {{ number_format((float) $invoice->credited_total, 2) }}</span></div>@endif
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

    @can('sales.reminders.send')
        @if ($invoice->balance_due > 0 && ! $invoice->status?->isTerminal() && $invoice->billing_email && $reminderRules->isNotEmpty())
            <div data-invoice-reminder-modal class="fixed inset-0 z-50 hidden p-4" role="dialog" aria-modal="true" aria-labelledby="invoice-reminder-title">
                <button type="button" data-invoice-reminder-close class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" aria-label="Close payment reminder"></button>
                <section class="relative mx-auto mt-[8vh] w-full max-w-lg overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between border-b border-slate-200 p-5 dark:border-slate-800"><div><p class="text-xs font-semibold uppercase tracking-[0.12em] text-amber-700 dark:text-amber-300">Customer communication</p><h2 id="invoice-reminder-title" class="mt-1 text-xl font-semibold text-slate-950 dark:text-white">Send payment reminder</h2></div><button type="button" data-invoice-reminder-close class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" aria-label="Close"><x-icon name="x" class="size-5" /></button></div>
                    <form method="POST" action="{{ route('sales.invoices.reminder', $invoice) }}" class="space-y-4 p-5">@csrf
                        <div class="grid gap-3 rounded-lg bg-slate-50 p-4 text-sm dark:bg-slate-800/70 sm:grid-cols-2"><div><p class="text-xs text-slate-500">Recipient</p><p class="mt-1 break-all font-semibold text-slate-950 dark:text-white">{{ $invoice->billing_email }}</p></div><div><p class="text-xs text-slate-500">Outstanding balance</p><p class="mt-1 font-semibold text-slate-950 dark:text-white">{{ $invoice->currency }} {{ number_format((float) $invoice->balance_due, 2) }}</p></div><div><p class="text-xs text-slate-500">Invoice</p><p class="mt-1 font-semibold text-slate-950 dark:text-white">{{ $invoice->invoice_number }}</p></div><div><p class="text-xs text-slate-500">Secure link</p><p class="mt-1 font-semibold text-teal-700 dark:text-teal-300">Included</p></div></div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Reminder type<select name="stage" class="mt-2 block w-full">@foreach($reminderRules as $rule)<option value="{{ $rule->stage->value }}">{{ $rule->stage->label() }}</option>@endforeach</select></label>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Optional note<textarea name="note" maxlength="1000" rows="3" class="mt-2 block w-full" placeholder="Add a short, customer-safe note if needed."></textarea></label>
                        <label class="inline-flex items-center gap-3 text-sm text-slate-700 dark:text-slate-200"><input type="hidden" name="attach_pdf" value="0"><input type="checkbox" name="attach_pdf" value="1" checked>Attach the active invoice PDF</label>
                        <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:justify-end"><button type="button" data-invoice-reminder-close class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Cancel</button><button class="rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-semibold text-slate-950 hover:bg-amber-400">Queue reminder</button></div>
                    </form>
                </section>
            </div>
        @endif
    @endcan
@endsection

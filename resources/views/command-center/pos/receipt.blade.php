@extends('layouts.pos')

@section('title', 'Receipt '.$sale->sale_number)

@section('content')
<main class="min-h-screen bg-slate-100 p-4 sm:p-8">
    <div class="mx-auto max-w-2xl">
        <div class="pos-print-controls mb-4 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('pos.terminal') }}" class="pos-secondary-action">New sale</a>

            <div class="flex flex-wrap items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">Print layout</span>
                <button type="button" data-pos-receipt-width="standard" class="pos-payment-method is-selected">Standard</button>
                <button type="button" data-pos-receipt-width="80mm" class="pos-payment-method">80mm</button>
                <button type="button" data-pos-receipt-width="58mm" class="pos-payment-method">58mm</button>
                <a href="{{ route('pos.receipts.pdf', $sale) }}" class="pos-secondary-action">PDF</a>
                <button onclick="window.print()" class="pos-primary-action">
                    <x-icon name="printer" class="mr-2 size-4" />
                    Print
                </button>
            </div>
        </div>

        <article class="theme-preserve-light pos-receipt rounded-xl bg-white p-6 text-slate-950 shadow-[0_8px_28px_rgb(15_23_42_/_0.08)] sm:p-8">
            @php
                $receiptLogo = ($branding['show_logo'] ?? false) ? ($branding['data_uri'] ?? null) : null;
                $receiptAlignment = in_array($branding['logo_position'] ?? null, ['left', 'center', 'right'], true) ? $branding['logo_position'] : 'center';
                $receiptDimensions = ['small' => 'max-h-7 max-w-20', 'medium' => 'max-h-10 max-w-28', 'large' => 'max-h-12 max-w-36'][$branding['logo_size'] ?? 'medium'] ?? 'max-h-10 max-w-28';
                $showReceiptName = ($branding['show_company_name'] ?? true) || ! $receiptLogo;
            @endphp
            <header class="border-b border-dashed border-slate-300 pb-5" style="text-align: {{ $receiptAlignment }};">
                @if($receiptLogo)<img src="{{ $receiptLogo }}" alt="{{ $sale->company->name }} logo" class="{{ $receiptDimensions }} mx-auto mb-2 w-auto object-contain @if($receiptAlignment === 'left') mr-auto ml-0 @elseif($receiptAlignment === 'right') ml-auto mr-0 @endif">@endif
                @if($showReceiptName)<p class="text-xl font-bold text-slate-950">{{ $gst?->legal_name ?: $sale->company->name ?? config('app.name') }}</p>@endif
                <p class="mt-1 text-sm text-slate-500">{{ $sale->branch?->name }}</p>
                @if($gst?->gstin)<p class="mt-1 text-xs text-slate-400">GSTIN {{ $gst->gstin }}</p>@endif
            </header>

            <section class="grid grid-cols-2 gap-x-5 gap-y-2 border-b border-dashed border-slate-300 py-4 text-sm">
                <div><span class="text-slate-400">Bill</span><p class="font-semibold">{{ $sale->receipt_number ?: $sale->sale_number }}</p></div>
                <div class="text-right"><span class="text-slate-400">Date</span><p class="font-semibold">{{ $sale->completed_at?->format('d M Y, h:i A') }}</p></div>
                <div><span class="text-slate-400">Cashier</span><p class="font-semibold">{{ $sale->completer?->name ?? 'Not recorded' }}</p></div>
                <div class="text-right"><span class="text-slate-400">Customer</span><p class="font-semibold">{{ $sale->customer_name_snapshot ?: $sale->customer?->display_name ?: 'Walk-in customer' }}</p><p class="text-xs text-slate-500">{{ $sale->customer_mobile_snapshot ?: $sale->customer?->phone }}</p>@if($sale->customer?->gstin)<p class="text-xs text-slate-500">GSTIN {{ $sale->customer->gstin }}</p>@endif</div>
            </section>

            <section class="divide-y divide-slate-100">
                @foreach($sale->items as $item)
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-4 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-800">{{ $item->product_name }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $item->sku }} · {{ number_format($item->quantity, 2) }} × {{ number_format($item->unit_price, 2) }}</p>
                            <p class="mt-0.5 text-xs text-slate-400">Discount {{ number_format($item->discount_amount, 2) }} · Tax {{ number_format($item->tax_amount, 2) }}</p>
                        </div>
                        <strong class="text-right">{{ number_format($item->line_total, 2) }}</strong>
                    </div>
                @endforeach
            </section>

            <section class="mt-3 space-y-2 border-t border-slate-200 pt-4 text-sm">
                <div class="flex justify-between text-slate-500"><span>Subtotal</span><span>{{ number_format($sale->subtotal, 2) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>Item / promotion discount</span><span>{{ number_format($sale->discount_amount, 2) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>Taxable amount</span><span>{{ number_format($sale->taxable_amount, 2) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>CGST</span><span>{{ number_format($sale->cgst_total, 2) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>SGST</span><span>{{ number_format($sale->sgst_total, 2) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>IGST</span><span>{{ number_format($sale->igst_total, 2) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>Cess</span><span>{{ number_format($sale->cess_total, 2) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>Paid</span><span>{{ number_format($sale->paid_amount, 2) }}</span></div>
                <div class="flex justify-between text-slate-500"><span>Change</span><span>{{ number_format($sale->change_amount, 2) }}</span></div>
                <div class="flex justify-between border-t border-slate-200 pt-3 text-xl font-bold text-slate-950"><span>Total</span><span>{{ number_format($sale->total_amount, 2) }}</span></div>
            </section>

            <section class="mt-5 rounded-lg bg-slate-50 p-3 text-sm">
                <p class="font-semibold text-slate-700">Payment</p>
                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-slate-600">
                    @forelse($sale->payments as $payment)
                        <span>{{ strtoupper($payment->payment_method) }} {{ number_format($payment->amount, 2) }}@if($payment->reference) · {{ $payment->reference }}@endif</span>
                    @empty
                        <span>No payment details recorded.</span>
                    @endforelse
                </div>
            </section>

            @if($sale->returns->isNotEmpty())
                <section class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <div class="flex flex-wrap items-center justify-between gap-3"><div><p class="font-semibold">Return history</p><p class="mt-1">Returned {{ number_format($sale->returned_amount,2) }} · {{ str($sale->return_status)->headline() }} return</p></div><a href="{{ route('pos.returns.index', ['q' => $sale->receipt_number ?: $sale->sale_number]) }}" class="font-semibold text-teal-700">Open returns</a></div>
                    <div class="mt-3 space-y-2">@foreach($sale->returns as $return)<a href="{{ route('pos.returns.show',$return) }}" class="flex items-center justify-between rounded-lg bg-white/80 px-3 py-2"><span class="font-semibold">{{ $return->return_number }}</span><span>{{ number_format($return->refund_total,2) }} · {{ str($return->status)->replace('_',' ')->title() }}</span></a>@endforeach</div>
                </section>
            @elseif($sale->status === 'completed' && auth()->user()->can('pos.returns.create'))
                <section class="mt-5 rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900"><p class="font-semibold">Need to reverse some or all of this sale?</p><p class="mt-1">Use the controlled return workflow so stock, refunds, and GST adjustments remain auditable.</p><a href="{{ route('pos.returns.create',['sale'=>$sale->id]) }}" class="mt-3 inline-block font-semibold text-teal-700">Start a return</a></section>
            @endif

            <footer class="mt-6 border-t border-dashed border-slate-300 pt-5 text-center text-sm text-slate-500">
                <p>Thank you for shopping with us.</p>
                <p class="mt-1 text-xs">Loyalty and wallet settlement are displayed once enabled for this POS workflow.</p>
            </footer>
        </article>

        @if($sale->status === 'voided')
            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-semibold">This sale was voided.</p>
                <p class="mt-1">{{ $sale->void_reason }}</p>
                <p class="mt-2">Use the returns/refund workflow for any stock or payment reversal.</p>
            </div>
        @elseif(auth()->user()->can('pos.sales.void'))
            <form method="POST" action="{{ route('pos.sales.void', $sale) }}" class="pos-print-controls mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm" onsubmit="return confirm('Void this completed sale? Stock and payments are handled only through the returns/refund workflow.');">
                @csrf
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label class="min-w-0 flex-1 text-sm font-semibold text-slate-700">
                        Void reason
                        <input required name="reason" maxlength="1000" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500" placeholder="Explain why this completed sale is being voided." />
                    </label>
                    <button type="submit" class="rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-800 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:ring-offset-2">Void sale</button>
                </div>
            </form>
        @endif

        <div class="pos-print-controls mt-4 flex justify-center">
            <a href="{{ route('pos.dashboard') }}" class="text-sm font-semibold text-teal-700">Back to POS dashboard</a>
        </div>
    </div>
</main>
@endsection

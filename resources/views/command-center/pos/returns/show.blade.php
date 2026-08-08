@extends('layouts.pos')

@section('title', 'Return '.$return->return_number)

@section('content')
<main class="min-h-screen bg-slate-50 p-4 sm:p-6 lg:p-8">
    <div class="mx-auto max-w-5xl">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('pos.returns.index') }}" class="text-sm font-semibold text-teal-700">Returns</a>
                <h1 class="mt-2 text-2xl font-bold text-slate-950">{{ $return->return_number }}</h1>
                <p class="mt-1 text-sm text-slate-500">Original sale {{ $return->originalSale->receipt_number ?: $return->originalSale->sale_number }} · {{ $return->return_date?->format('d M Y') }}</p>
            </div>
            <div class="flex gap-2">
                <span class="rounded-full bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white">{{ str($return->status)->replace('_', ' ')->title() }}</span>
                @if($return->status === 'completed')
                    <a href="{{ route('pos.returns.pdf', $return) }}" class="pos-secondary-action">Download credit note</a>
                    <button onclick="window.print()" class="pos-secondary-action">Print</button>
                @endif
            </div>
        </header>

        @if(session('status'))
            <div class="mt-5 rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-800">{{ session('status') }}</div>
        @endif

        <section class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Refund total</p><p class="mt-2 text-xl font-bold text-slate-950">{{ number_format($return->refund_total, 2) }} {{ $return->currency }}</p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">GST adjustment</p><p class="mt-2 text-xl font-bold text-slate-950">{{ number_format($return->tax_adjustment_total, 2) }}</p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Store credit</p><p class="mt-2 text-xl font-bold text-slate-950">{{ number_format($return->store_credit_total, 2) }}</p></div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Credit note</p><p class="mt-2 text-lg font-bold text-slate-950">{{ $return->credit_note_number ?: 'Issued on completion' }}</p></div>
        </section>

        <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Returned items</h2><p class="mt-1 text-sm text-slate-500">Tax and pricing values are preserved from the original sale.</p></div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.08em] text-slate-500"><tr><th class="p-4">Item</th><th class="p-4 text-right">Qty</th><th class="p-4">Stock action</th><th class="p-4 text-right">Discount</th><th class="p-4 text-right">Tax</th><th class="p-4 text-right">Refund</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($return->items as $item)
                            <tr>
                                <td class="p-4"><p class="font-semibold text-slate-800">{{ $item->product_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $item->hsn_sac ?: 'HSN not recorded' }} @if($item->condition_note) · {{ $item->condition_note }} @endif</p></td>
                                <td class="p-4 text-right">{{ number_format($item->return_quantity, 3) }}</td>
                                <td class="p-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ str($item->stock_disposition)->replace('_', ' ')->title() }}</span></td>
                                <td class="p-4 text-right">{{ number_format($item->discount_adjustment, 2) }}</td>
                                <td class="p-4 text-right">{{ number_format($item->tax_adjustment, 2) }}</td>
                                <td class="p-4 text-right font-semibold">{{ number_format($item->line_refund_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-semibold text-slate-900">Refund record</h2><div class="mt-4 space-y-3">@foreach($return->refunds as $refund)<div class="flex items-center justify-between border-b border-slate-100 pb-3 text-sm"><div><p class="font-semibold text-slate-800">{{ strtoupper($refund->method) }}</p><p class="text-xs text-slate-500">{{ $refund->external_reference ?: 'Manual record' }}</p></div><div class="text-right"><p class="font-semibold">{{ number_format($refund->amount, 2) }}</p><p class="text-xs text-slate-500">{{ ucfirst($refund->status) }}</p></div></div>@endforeach</div></div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="font-semibold text-slate-900">Review trail</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div><dt class="text-slate-500">Requested by</dt><dd class="font-medium text-slate-800">{{ $return->requester?->name }}</dd></div>
                    @if($return->approver)
                        <div><dt class="text-slate-500">Approved by</dt><dd class="font-medium text-slate-800">{{ $return->approver->name }} · {{ $return->approved_at?->format('d M Y, h:i A') }}</dd></div>
                    @endif
                    @if($return->completer)
                        <div><dt class="text-slate-500">Completed by</dt><dd class="font-medium text-slate-800">{{ $return->completer->name }} · {{ $return->completed_at?->format('d M Y, h:i A') }}</dd></div>
                    @endif
                </dl>
            </div>
        </section>

        <div class="mt-6 flex flex-wrap justify-end gap-3">
            @if(in_array($return->status, ['draft', 'pending_approval'], true) && auth()->user()->can('pos.returns.cancel') && ($return->requested_by === auth()->id() || auth()->user()->can('pos.returns.approve')))
                <form method="POST" action="{{ route('pos.returns.cancel', $return) }}" onsubmit="return confirm('Cancel this unposted return? No stock or refund will be posted.');">@csrf<button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Cancel return</button></form>
            @endif
            @if($return->status === 'pending_approval' && auth()->user()->can('pos.returns.approve'))
                <form method="POST" action="{{ route('pos.returns.reject', $return) }}" class="flex gap-2">@csrf<input required name="reason" maxlength="1000" class="rounded-lg border-slate-300 text-sm" placeholder="Rejection reason"><button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700">Reject</button></form>
                <form method="POST" action="{{ route('pos.returns.approve', $return) }}">@csrf<button class="pos-primary-action">Approve return</button></form>
            @endif
            @if($return->status === 'approved' && auth()->user()->can('pos.returns.complete'))
                <form method="POST" action="{{ route('pos.returns.complete', $return) }}" onsubmit="return confirm('Complete this return? Stock and refund records will be posted once.');">@csrf<button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Complete return</button></form>
            @endif
        </div>
    </div>
</main>
@endsection

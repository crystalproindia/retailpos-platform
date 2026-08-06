@extends('layouts.pos')

@section('title', 'POS Returns')

@section('content')
<main class="min-h-screen bg-slate-50 p-4 sm:p-6 lg:p-8">
    <div class="mx-auto max-w-7xl">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div><a href="{{ route('pos.dashboard') }}" class="text-sm font-semibold text-teal-700">POS dashboard</a><h1 class="mt-2 text-2xl font-bold text-slate-950">Returns and refunds</h1><p class="mt-1 text-sm text-slate-500">Find a completed sale, review its remaining quantities, and create a controlled return.</p></div>
            @can('pos.returns.settings.manage')<a href="{{ route('pos.returns.settings') }}" class="pos-secondary-action">Return controls</a>@endcan
        </header>

        <form method="GET" class="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-4">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="rounded-lg border-slate-300 text-sm" placeholder="Receipt, invoice, customer or mobile" aria-label="Search completed sales">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border-slate-300 text-sm" aria-label="From date">
            <div class="flex gap-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm" aria-label="To date"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Search</button></div>
        </form>

        <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Eligible sales</h2><p class="mt-1 text-sm text-slate-500">Only completed sales within your outlet access are shown.</p></div>
            <div class="divide-y divide-slate-100">
                @forelse($sales as $sale)
                    @php($returned = (float) $sale->returns->sum('refund_total'))
                    <article class="flex flex-col gap-4 p-5 transition hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0"><p class="font-semibold text-slate-900">{{ $sale->receipt_number ?: $sale->sale_number }}</p><p class="mt-1 text-sm text-slate-600">{{ $sale->customer_name_snapshot ?: $sale->customer?->display_name ?: 'Walk-in customer' }} <span class="text-slate-300">•</span> {{ $sale->completed_at?->format('d M Y, h:i A') }}</p><p class="mt-1 text-xs text-slate-500">{{ $sale->payments->pluck('payment_method')->map(fn($method) => strtoupper($method))->join(', ') ?: 'Payment not recorded' }}</p></div>
                        <div class="flex items-center justify-between gap-4 sm:justify-end"><div class="text-right text-sm"><p class="font-semibold text-slate-900">{{ number_format($sale->total_amount, 2) }} {{ $sale->currency }}</p><p class="mt-1 text-xs text-slate-500">Returned {{ number_format($returned, 2) }} · Remaining {{ number_format(max(0, (float) $sale->total_amount - $returned), 2) }}</p></div><a href="{{ route('pos.returns.create', ['sale' => $sale->id]) }}" class="pos-primary-action">Start return</a></div>
                    </article>
                @empty
                    <div class="p-12 text-center"><p class="font-semibold text-slate-700">No eligible sales found</p><p class="mt-1 text-sm text-slate-500">Search by receipt, sale reference, or customer details.</p></div>
                @endforelse
            </div>
        </section>

        <section class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Recent returns</h2></div>
            <div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-[0.08em] text-slate-500"><tr><th class="p-4">Return</th><th class="p-4">Original sale</th><th class="p-4">Customer</th><th class="p-4 text-right">Amount</th><th class="p-4">Status</th></tr></thead><tbody class="divide-y divide-slate-100">@forelse($recentReturns as $return)<tr class="hover:bg-slate-50"><td class="p-4"><a href="{{ route('pos.returns.show', $return) }}" class="font-semibold text-teal-700">{{ $return->return_number }}</a></td><td class="p-4 text-slate-600">{{ $return->originalSale?->receipt_number ?: $return->originalSale?->sale_number }}</td><td class="p-4 text-slate-600">{{ $return->customer?->display_name ?: 'Walk-in' }}</td><td class="p-4 text-right font-semibold">{{ number_format($return->refund_total, 2) }}</td><td class="p-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ str($return->status)->replace('_', ' ')->title() }}</span></td></tr>@empty<tr><td colspan="5" class="p-10 text-center text-slate-500">No returns have been created yet.</td></tr>@endforelse</tbody></table></div>
            @if($recentReturns->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $recentReturns->links() }}</div>@endif
        </section>
    </div>
</main>
@endsection

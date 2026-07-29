@extends('layouts.pos')

@section('title', 'POS Sales History')

@section('content')
<main class="min-h-screen bg-slate-50 p-4 sm:p-6 lg:p-8">
    <div class="mx-auto max-w-7xl">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <a href="{{ route('pos.dashboard') }}" class="text-sm font-semibold text-teal-700">POS dashboard</a>
                <h1 class="mt-2 text-2xl font-bold text-slate-950">Sales history</h1>
                <p class="mt-1 text-sm text-slate-500">Completed and voided counter bills for your company.</p>
            </div>
            <a href="{{ route('pos.terminal') }}" class="pos-primary-action">Open terminal</a>
        </header>

        <form method="GET" class="mt-6 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-5">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="rounded-lg border-slate-300 text-sm" placeholder="Receipt, customer or mobile">
            <select name="status" class="rounded-lg border-slate-300 text-sm"><option value="">All statuses</option><option value="completed" @selected(($filters['status'] ?? null) === 'completed')>Completed</option><option value="voided" @selected(($filters['status'] ?? null) === 'voided')>Voided</option></select>
            <select name="payment_method" class="rounded-lg border-slate-300 text-sm"><option value="">All payments</option>@foreach(['cash' => 'Cash', 'card' => 'Card', 'upi' => 'UPI', 'bank_transfer' => 'Bank transfer', 'wallet' => 'Wallet', 'credit' => 'Credit', 'other' => 'Other'] as $value => $label)<option value="{{ $value }}" @selected(($filters['payment_method'] ?? null) === $value)>{{ $label }}</option>@endforeach</select>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded-lg border-slate-300 text-sm" aria-label="From date">
            <div class="flex gap-2"><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm" aria-label="To date"><button class="rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white">Filter</button></div>
        </form>

        <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.08em] text-slate-500"><tr><th class="p-4">Receipt</th><th class="p-4">Store / register</th><th class="p-4">Customer</th><th class="p-4">Payment</th><th class="p-4">Cashier</th><th class="p-4 text-right">Total</th><th class="p-4">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($sales as $sale)
                            <tr class="hover:bg-slate-50">
                                <td class="p-4"><a href="{{ route('pos.receipts.show', $sale) }}" class="font-semibold text-teal-700">{{ $sale->receipt_number ?: $sale->sale_number }}</a><p class="mt-1 text-xs text-slate-400">{{ $sale->completed_at?->format('d M Y, h:i A') }}</p></td>
                                <td class="p-4 text-slate-600">{{ $sale->branch?->name ?? 'Not recorded' }}<p class="mt-1 text-xs text-slate-400">{{ $sale->register?->name ?? 'Legacy sale' }}</p></td>
                                <td class="p-4 text-slate-600">{{ $sale->customer_name_snapshot ?: $sale->customer?->display_name ?: 'Walk-in' }}</td>
                                <td class="p-4 text-slate-600">{{ $sale->payments->pluck('payment_method')->map(fn ($method) => strtoupper($method))->join(', ') ?: 'Not recorded' }}</td>
                                <td class="p-4 text-slate-600">{{ $sale->completer?->name ?? 'Not recorded' }}</td>
                                <td class="p-4 text-right font-semibold">{{ number_format($sale->total_amount, 2) }} {{ $sale->currency }}</td>
                                <td class="p-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $sale->status === 'voided' ? 'bg-rose-100 text-rose-700' : 'bg-teal-100 text-teal-700' }}">{{ ucfirst($sale->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-12 text-center text-slate-500">No POS sales match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($sales->hasPages())<div class="border-t border-slate-100 px-4 py-3">{{ $sales->links() }}</div>@endif
        </section>
    </div>
</main>
@endsection

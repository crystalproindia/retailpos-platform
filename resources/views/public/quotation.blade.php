<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $quotation->quotation_number }} | RetailPOS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <main class="mx-auto max-w-4xl p-4 sm:p-8">
        <article class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <header class="bg-slate-950 p-6 text-white sm:p-8">
                <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-start">
                    <div><p class="text-sm font-semibold uppercase text-teal-300">RetailPOS</p><h1 class="mt-2 text-2xl font-semibold">{{ $quotation->title }}</h1><p class="mt-2 text-sm text-slate-300">{{ $quotation->quotation_number }}</p></div>
                    <div class="text-sm text-slate-300"><p>Valid until</p><p class="mt-1 font-semibold text-white">{{ $quotation->valid_until?->format('d M Y') ?? 'No expiry specified' }}</p></div>
                </div>
            </header>
            <div class="p-6 sm:p-8">
                <section class="grid gap-6 border-b border-slate-200 pb-6 sm:grid-cols-2">
                    <div><p class="text-xs font-semibold uppercase text-slate-500">Prepared for</p><p class="mt-2 font-semibold">{{ $quotation->customer_name ?? 'Customer' }}</p><p class="mt-1 text-sm text-slate-600">{{ $quotation->customer_company }}</p><p class="mt-1 text-sm text-slate-600">{{ $quotation->customer_email }}</p></div>
                    <div class="sm:text-right"><p class="text-xs font-semibold uppercase text-slate-500">Proposal total</p><p class="mt-2 text-2xl font-semibold">{{ $quotation->currency }} {{ number_format((float) $quotation->grand_total, 2) }}</p><p class="mt-1 text-sm text-slate-600">{{ $quotation->items->count() }} line items</p></div>
                </section>
                <section class="mt-6 overflow-x-auto">
                    <table class="min-w-full text-sm"><thead class="border-b border-slate-200 text-left text-xs uppercase text-slate-500"><tr><th class="pb-3">Item</th><th class="pb-3 text-right">Qty</th><th class="pb-3 text-right">Price</th><th class="pb-3 text-right">Total</th></tr></thead><tbody class="divide-y divide-slate-100">
                        @foreach ($quotation->items as $item)
                            <tr><td class="py-4"><p class="font-medium">{{ $item->name }}</p>@if ($item->description)<p class="mt-1 text-xs text-slate-500">{{ $item->description }}</p>@endif</td><td class="py-4 text-right">{{ number_format((float) $item->quantity, 3) }}</td><td class="py-4 text-right">{{ number_format((float) $item->unit_price, 2) }}</td><td class="py-4 text-right font-semibold">{{ number_format((float) $item->line_total, 2) }}</td></tr>
                        @endforeach
                    </tbody></table>
                </section>
                <section class="ml-auto mt-6 max-w-sm space-y-2 text-sm"><div class="flex justify-between text-slate-500"><span>Subtotal</span><span>{{ $quotation->currency }} {{ number_format((float) $quotation->subtotal, 2) }}</span></div><div class="flex justify-between text-slate-500"><span>Discount</span><span>{{ $quotation->currency }} {{ number_format((float) $quotation->discount_total, 2) }}</span></div><div class="flex justify-between text-slate-500"><span>Tax</span><span>{{ $quotation->currency }} {{ number_format((float) $quotation->tax_total, 2) }}</span></div><div class="flex justify-between border-t border-slate-200 pt-3 text-lg font-semibold"><span>Grand total</span><span>{{ $quotation->currency }} {{ number_format((float) $quotation->grand_total, 2) }}</span></div></section>
                @if (session('status'))<div class="mt-6 rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm font-medium text-teal-900">{{ session('status') }}</div>@endif
                @if ($quotation->valid_until?->isPast())<div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">This quotation expired on {{ $quotation->valid_until->format('d M Y') }}. Please contact RetailPOS for an updated quotation.</div>@endif
                @if ($quotation->notes)<section class="mt-8 border-t border-slate-200 pt-6"><h2 class="font-semibold">Notes</h2><p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $quotation->notes }}</p></section>@endif
                @if ($quotation->terms_conditions)<section class="mt-8 border-t border-slate-200 pt-6"><h2 class="font-semibold">Terms and conditions</h2><p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $quotation->terms_conditions }}</p></section>@endif
            </div>
            <footer class="border-t border-slate-200 bg-slate-50 p-6">
                <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center"><div><p class="text-sm font-semibold">Questions about this quotation?</p><p class="mt-2 text-sm text-slate-600">India: +91 8072682244 · Malaysia: +60 104305163 · Singapore: +65 92475024<br>info@retailpos.biz · global@retailpos.biz</p></div><a href="{{ route('quotations.public.pdf', $publicToken) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-semibold text-slate-800">Download PDF</a></div>
                @if (! $quotation->public_responded_at && ! $quotation->valid_until?->isPast())
                    <form method="POST" action="{{ route('quotations.public.decision', $publicToken) }}" class="mt-6 grid gap-3 border-t border-slate-200 pt-6">@csrf
                        <div class="grid gap-3 sm:grid-cols-2"><input name="name" required maxlength="120" placeholder="Your name" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><select name="decision" required class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="accepted">Accept quotation</option><option value="rejected">Reject quotation</option></select></div>
                        <textarea name="message" rows="3" maxlength="2000" placeholder="Optional message" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></textarea><input name="rejection_reason" maxlength="1000" placeholder="Reason if rejecting" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="confirm" value="1" required> I confirm this decision is authorized.</label><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Submit decision</button>
                    </form>
                @elseif ($quotation->public_responded_at)
                    <p class="mt-6 border-t border-slate-200 pt-6 text-sm font-medium text-slate-700">A {{ $quotation->status?->label() }} decision was recorded on {{ $quotation->public_responded_at->format('d M Y, h:i A') }}.</p>
                @endif
            </footer>
        </article>
    </main>
</body>
</html>

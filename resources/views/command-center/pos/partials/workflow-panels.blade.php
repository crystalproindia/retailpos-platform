<div data-pos-hold-panel class="pos-workflow-panel hidden" role="dialog" aria-modal="true" aria-labelledby="hold-sale-title">
    <button type="button" data-pos-close-panel class="pos-modal-backdrop" aria-label="Close hold sale"></button>
    <section class="pos-shortcuts-sheet">
        <header class="pos-workflow-heading">
            <div><p class="text-xs font-semibold uppercase text-teal-700 dark:text-teal-300">Pause this bill</p><h2 id="hold-sale-title" class="mt-1 text-xl font-bold text-slate-950 dark:text-slate-100">Hold sale</h2></div>
            <button type="button" data-pos-close-panel class="pos-icon-button" aria-label="Close hold sale"><x-icon name="x" class="size-4" /></button>
        </header>
        <label class="mt-4 block text-sm font-semibold text-slate-700 dark:text-slate-200">Reference or note
            <input data-pos-hold-note class="mt-2 w-full" maxlength="500" placeholder="Optional, for example: customer returning in 10 minutes">
        </label>
        <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">The cart, selected customer, discounts, and current quantities will be preserved.</p>
        <button type="button" data-pos-confirm-hold class="pos-secondary-action mt-5 w-full border-teal-700 text-teal-800 dark:text-teal-200">Hold this sale</button>
    </section>
</div>

<div data-pos-held-panel class="pos-workflow-panel hidden" role="dialog" aria-modal="true" aria-labelledby="held-sales-title">
    <button type="button" data-pos-close-panel class="pos-modal-backdrop" aria-label="Close held sales"></button>
    <section class="pos-workflow-sheet">
        <header class="pos-workflow-heading">
            <div><p class="text-xs font-semibold uppercase text-teal-700 dark:text-teal-300">Continue a sale</p><h2 id="held-sales-title" class="mt-1 text-xl font-bold text-slate-950 dark:text-slate-100">Held sales</h2></div>
            <button type="button" data-pos-close-panel class="pos-icon-button" aria-label="Close held sales"><x-icon name="x" class="size-4" /></button>
        </header>
        <div class="pos-held-list">
            @forelse($heldSales as $sale)
                <article class="pos-held-row">
                    <div class="min-w-0"><p class="truncate text-sm font-bold text-slate-900 dark:text-slate-100">{{ $sale->customer?->display_name ?? 'Walk-in customer' }}</p><p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $sale->sale_number }} · {{ $sale->items->sum('quantity') }} items · {{ auth()->user()->name }} · {{ $sale->held_at?->diffForHumans() }}</p>@if($sale->notes)<p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $sale->notes }}</p>@endif</div>
                    <div class="shrink-0 text-right"><p class="font-bold tabular-nums text-slate-900 dark:text-slate-100">{{ number_format($sale->total_amount, 2) }}</p><a href="{{ route('pos.held.resume', $sale) }}" class="mt-2 inline-flex min-h-10 items-center rounded-lg bg-teal-700 px-3 text-xs font-bold text-white hover:bg-teal-800">Resume</a></div>
                </article>
            @empty
                <div class="pos-empty-list"><x-icon name="pos" class="size-8 text-slate-300" /><p class="mt-3 font-semibold text-slate-700 dark:text-slate-200">No held sales</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Bills held from this cashier account appear here.</p></div>
            @endforelse
        </div>
        <a href="{{ route('pos.held.index') }}" class="pos-secondary-action mt-4 w-full">Open full held-sales list</a>
    </section>
</div>

<div data-pos-shortcuts-panel class="pos-workflow-panel hidden" role="dialog" aria-modal="true" aria-labelledby="pos-shortcuts-title">
    <button type="button" data-pos-close-panel class="pos-modal-backdrop" aria-label="Close keyboard shortcuts"></button>
    <section class="pos-shortcuts-sheet">
        <header class="pos-workflow-heading"><div><p class="text-xs font-semibold uppercase text-teal-700 dark:text-teal-300">Cashier controls</p><h2 id="pos-shortcuts-title" class="mt-1 text-xl font-bold text-slate-950 dark:text-slate-100">Keyboard shortcuts</h2></div><button type="button" data-pos-close-panel class="pos-icon-button" aria-label="Close keyboard shortcuts"><x-icon name="x" class="size-4" /></button></header>
        <dl class="pos-shortcut-list">
            @foreach([['/ or F2','Product search'],['F4','Customer search'],['F6','Held sales'],['F8','Order discount'],['F9','Payment'],['Esc','Close panel']] as [$key,$label])<div><dt>{{ $key }}</dt><dd>{{ $label }}</dd></div>@endforeach
        </dl>
    </section>
</div>

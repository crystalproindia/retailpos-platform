@extends('layouts.admin')
@section('title', 'Move Stock')
@section('page-title', 'Move Stock')
@section('breadcrumbs')<span>/</span><a href="{{ route('inventory.dashboard') }}">Inventory</a><span>/</span><a href="{{ route('inventory.transfers.index') }}">Transfers</a><span>/</span><span>New</span>@endsection
@section('content')
@include('command-center.inventory.partials.nav')
<form method="POST" action="{{ route('inventory.transfers.store') }}" id="transfer-form" class="mx-auto max-w-6xl space-y-5 pb-24">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Str::uuid()) }}">
    @if($errors->any())<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><p class="font-semibold">Please check the transfer.</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-4"><h2 class="text-lg font-semibold">Where is the stock moving?</h2><p class="mt-1 text-sm text-slate-500">Choose a source and destination. A warehouse may be linked to a store or operate centrally.</p></div>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="text-sm font-semibold">From<select id="source-warehouse" name="source_warehouse_id" required class="mt-1 min-h-12 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"><option value="">Choose source</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string)old('source_warehouse_id', $prefill['source_warehouse_id'] ?? '')===(string)$warehouse->id)>{{ $warehouse->name }}{{ $warehouse->branch ? ' · '.$warehouse->branch->name : ' · Central warehouse' }}</option>@endforeach</select><span class="mt-1 block text-xs font-normal text-slate-500">Stock leaves this location only when dispatched.</span></label>
            <label class="text-sm font-semibold">To<select id="destination-warehouse" name="destination_warehouse_id" required class="mt-1 min-h-12 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"><option value="">Choose destination</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string)old('destination_warehouse_id', $prefill['destination_warehouse_id'] ?? '')===(string)$warehouse->id)>{{ $warehouse->name }}{{ $warehouse->branch ? ' · '.$warehouse->branch->name : ' · Central warehouse' }}</option>@endforeach</select><span class="mt-1 block text-xs font-normal text-slate-500">Stock becomes available here only after receipt.</span></label>
            <label class="text-sm font-semibold">Source bin <span class="font-normal text-slate-400">optional</span><select id="source-location" name="source_stock_location_id" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"><option value="">Main stock area</option>@foreach($locations as $location)<option data-warehouse="{{ $location->warehouse_id }}" value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>@endforeach</select><span class="mt-1 block text-xs font-normal text-slate-500">Choose the bin that currently holds the products. Availability below follows this selection.</span></label>
            <label class="text-sm font-semibold">Destination bin <span class="font-normal text-slate-400">optional</span><select id="destination-location" name="destination_stock_location_id" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"><option value="">Main stock area</option>@foreach($locations as $location)<option data-warehouse="{{ $location->warehouse_id }}" value="{{ $location->id }}">{{ $location->code }} · {{ $location->name }}</option>@endforeach</select><span class="mt-1 block text-xs font-normal text-slate-500">Received stock is placed in this bin.</span></label>
            <label class="text-sm font-semibold">Expected arrival <span class="font-normal text-slate-400">optional</span><input type="datetime-local" name="expected_arrival_at" value="{{ old('expected_arrival_at') }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="text-sm font-semibold">Transfer note <span class="font-normal text-slate-400">optional</span><input name="notes" value="{{ old('notes') }}" maxlength="3000" placeholder="Reason or delivery detail" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 p-5 dark:border-slate-800"><h2 class="text-lg font-semibold">Add products</h2><p class="mt-1 text-sm text-slate-500">Scan a barcode repeatedly to increase quantity, or search by product name or SKU.</p><div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]"><input id="barcode-input" autocomplete="off" placeholder="Scan barcode, SKU, or type a product name" class="min-h-12 w-full rounded-lg border-slate-300 text-base dark:border-slate-700 dark:bg-slate-950"><button type="button" id="add-product" class="min-h-12 rounded-lg bg-teal-600 px-5 font-semibold text-white transition hover:bg-teal-700">Add product</button></div><p id="scan-message" class="mt-2 min-h-5 text-sm text-slate-500" aria-live="polite"></p></div>
        <div id="transfer-items" class="divide-y divide-slate-100 dark:divide-slate-800"></div>
        <div id="empty-items" class="p-10 text-center text-sm text-slate-500">No products added yet. Scan or search above.</div>
    </section>

    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-2xl backdrop-blur lg:left-72 dark:border-slate-800 dark:bg-slate-950/95">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3"><div class="text-sm"><span id="sku-total" class="font-semibold">0 SKUs</span><span class="mx-2 text-slate-300">|</span><span id="quantity-total" class="text-slate-500">0 total units</span></div><div class="flex gap-2"><a href="{{ route('inventory.transfers.index') }}" class="inline-flex min-h-11 items-center px-3 text-sm font-semibold text-slate-600">Cancel</a><button class="min-h-11 rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save draft</button></div></div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let products = [];
    let serials = [];
    let batches = [];
    let stock = {};
    const rows = new Map();
    const input = document.getElementById('barcode-input');
    const container = document.getElementById('transfer-items');
    const message = document.getElementById('scan-message');

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const quantity = (productId, warehouseId) => Number(stock[`${warehouseId}-${productId}`] ?? 0).toFixed(3).replace(/\.000$/, '');
    const totals = () => {
        document.getElementById('empty-items').classList.toggle('hidden', rows.size > 0);
        document.getElementById('sku-total').textContent = `${rows.size} ${rows.size === 1 ? 'SKU' : 'SKUs'}`;
        const total = [...rows.values()].reduce((sum, row) => sum + Number(row.querySelector('input[type=number]').value || 0), 0);
        document.getElementById('quantity-total').textContent = `${total.toFixed(3).replace(/\.000$/, '')} total units`;
    };
    const refreshStock = () => rows.forEach((row, id) => {
        row.querySelector('[data-source-stock]').textContent = quantity(id, document.getElementById('source-warehouse').value);
        row.querySelector('[data-destination-stock]').textContent = quantity(id, document.getElementById('destination-warehouse').value);
    });
    const add = (product) => {
        if (rows.has(product.id)) {
            const field = rows.get(product.id).querySelector('input[type=number]'); field.value = (Number(field.value) + 1).toFixed(3).replace(/\.000$/, ''); field.dispatchEvent(new Event('input')); message.textContent = `${product.name} quantity increased.`; return;
        }
        const index = rows.size;
        const row = document.createElement('div'); row.className = 'grid gap-3 p-4 sm:grid-cols-[1fr_110px_110px_130px_44px] sm:items-center';
        const serialField = product.track_serials ? `<label class="sm:col-span-5 text-xs font-semibold text-slate-500">Serial numbers<select multiple data-serial-select name="items[${index}][serial_ids][]" class="mt-1 min-h-24 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></select><span class="mt-1 block font-normal">Select one serial for each unit being moved.</span></label>` : '';
        const batchField = product.track_batches ? `<label class="sm:col-span-5 text-xs font-semibold text-slate-500">Source batch<select required data-batch-select name="items[${index}][inventory_batch_id]" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Choose batch</option></select></label>` : '';
        row.innerHTML = `<input type="hidden" name="items[${index}][product_id]" value="${product.id}"><div class="flex items-center gap-3">${product.image ? `<img src="${escapeHtml(product.image)}" alt="" loading="lazy" class="size-11 shrink-0 rounded-lg bg-slate-100 object-cover">` : `<span class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 font-semibold text-slate-400">${escapeHtml(product.name).charAt(0)}</span>`}<div class="min-w-0"><p class="truncate font-semibold text-slate-900 dark:text-white">${escapeHtml(product.name)}</p><p class="mt-1 truncate text-xs text-slate-500">${escapeHtml(product.sku)}${product.barcode ? ' · '+escapeHtml(product.barcode) : ''}</p></div></div><div><span class="text-xs text-slate-500">Source</span><p data-source-stock class="font-semibold">0</p></div><div><span class="text-xs text-slate-500">Destination</span><p data-destination-stock class="font-semibold">0</p></div><label class="text-xs font-semibold text-slate-500">Quantity<input type="number" name="items[${index}][quantity]" value="1" min="0.001" step="0.001" required class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-base text-slate-900 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label><button type="button" aria-label="Remove ${escapeHtml(product.name)}" class="min-h-11 rounded-lg text-xl text-slate-400 transition hover:bg-rose-50 hover:text-rose-600">&times;</button>${batchField}${serialField}`;
        if (product.track_batches) { const select = row.querySelector('[data-batch-select]'); const sourceId = Number(document.getElementById('source-warehouse').value); batches.filter(batch => batch.product_id === product.id && batch.warehouse_id === sourceId).forEach(batch => select.add(new Option(`${batch.batch_number} · ${batch.quantity_available} available${batch.expires_at ? ' · expires '+batch.expires_at : ''}`, batch.id))); }
        if (product.track_serials) { const select = row.querySelector('[data-serial-select]'); const sourceId = Number(document.getElementById('source-warehouse').value); serials.filter(serial => serial.product_id === product.id && serial.warehouse_id === sourceId).forEach(serial => select.add(new Option(serial.serial_number, serial.id))); select.addEventListener('change', () => { row.querySelector('input[type=number]').value = select.selectedOptions.length; totals(); }); }
        row.querySelector('input[type=number]').addEventListener('input', totals); row.querySelector('button').addEventListener('click', () => { rows.delete(product.id); row.remove(); totals(); }); rows.set(product.id, row); container.appendChild(row); refreshStock(); totals(); message.textContent = `${product.name} added.`;
    };
    const findProduct = async () => {
        const term = input.value.trim();
        if (!term) return null;
        const query = new URLSearchParams({
            q: term,
            source_warehouse_id: document.getElementById('source-warehouse').value,
            destination_warehouse_id: document.getElementById('destination-warehouse').value,
            source_stock_location_id: document.getElementById('source-location').value,
            destination_stock_location_id: document.getElementById('destination-location').value,
        });
        const response = await fetch(`{{ route('inventory.transfers.products') }}?${query}`, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
        if (!response.ok) throw new Error('Product search is unavailable.');
        const result = await response.json();
        products = result.products ?? [];
        serials = result.serials ?? [];
        batches = result.batches ?? [];
        stock = {...stock, ...(result.stock ?? {})};
        const normalized = term.toLowerCase();
        return products.find(product => String(product.barcode ?? '').toLowerCase() === normalized || product.sku.toLowerCase() === normalized) || products[0] || null;
    };
    const submitSearch = async () => {
        if (!document.getElementById('source-warehouse').value || !document.getElementById('destination-warehouse').value) { message.textContent = 'Choose From and To before adding products.'; return; }
        message.textContent = 'Searching products...';
        try { const product = await findProduct(); if (product) { add(product); input.value = ''; } else { message.textContent = 'No matching active product found.'; } }
        catch (error) { message.textContent = error.message; }
        input.focus();
    };
    input.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); submitSearch(); } }); document.getElementById('add-product').addEventListener('click', submitSearch);
    ['source-warehouse','destination-warehouse','source-location','destination-location'].forEach(id => document.getElementById(id).addEventListener('change', () => { if (rows.size) { rows.forEach(row => row.remove()); rows.clear(); totals(); message.textContent = 'Products were cleared because the transfer location changed.'; } stock = {}; products = []; serials = []; batches = []; }));
    const filterLocations = (warehouseId, selectId) => { const select = document.getElementById(selectId); [...select.options].forEach((option, index) => { if (index) option.hidden = option.dataset.warehouse !== warehouseId; }); if (select.selectedOptions[0]?.hidden) select.value = ''; };
    document.getElementById('source-warehouse').addEventListener('change', event => filterLocations(event.target.value, 'source-location')); document.getElementById('destination-warehouse').addEventListener('change', event => filterLocations(event.target.value, 'destination-location'));
    filterLocations(document.getElementById('source-warehouse').value, 'source-location');
    filterLocations(document.getElementById('destination-warehouse').value, 'destination-location');
    @if($prefillProduct)
        input.value = @json($prefillProduct->sku);
        submitSearch().then(() => {
            const row = rows.get({{ $prefillProduct->id }});
            if (row) {
                const field = row.querySelector('input[type=number]');
                field.value = @json((string) ($prefill['quantity'] ?? 1));
                field.dispatchEvent(new Event('input'));
            }
        });
    @endif
});
</script>
@endsection

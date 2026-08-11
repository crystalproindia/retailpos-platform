@extends('layouts.admin')
@section('title', $transfer->transfer_number)
@section('page-title', 'Transfer '.$transfer->transfer_number)
@section('breadcrumbs')<span>/</span><a href="{{ route('inventory.dashboard') }}">Inventory</a><span>/</span><a href="{{ route('inventory.transfers.index') }}">Transfers</a><span>/</span><span>{{ $transfer->transfer_number }}</span>@endsection
@section('content')
@include('command-center.inventory.partials.nav')
@php
    $statuses = ['draft','pending_approval','approved','packing','in_transit','partially_received','received'];
    $current = array_search($transfer->status, $statuses, true);
    $terminal = in_array($transfer->status, ['rejected','cancelled'], true);
    $badge = match($transfer->status) { 'received' => 'bg-emerald-100 text-emerald-800', 'discrepancy','rejected','cancelled' => 'bg-rose-100 text-rose-800', 'in_transit','partially_received' => 'bg-indigo-100 text-indigo-800', 'pending_approval' => 'bg-amber-100 text-amber-800', default => 'bg-slate-100 text-slate-700' };
    $workflowItems = $transfer->items->values()->map(fn ($item, $index) => [
        'index' => $index,
        'name' => $item->product->name,
        'sku' => $item->product->sku,
        'barcode' => $item->product->barcode,
        'approved' => (float) $item->approved_quantity,
        'remaining' => (float) $item->in_transit_quantity,
    ]);
    $trackedItems = $transfer->items->values()->map(fn ($item, $index) => [
        'index' => $index,
        'tracked' => (bool) $item->product->track_serials,
        'serials' => $item->serialNumbers->where('status', 'in_transit')->values()->map(fn ($serial) => [
            'id' => $serial->id,
            'number' => $serial->serial_number,
        ]),
    ]);
@endphp
<div class="mx-auto max-w-7xl space-y-6">
    @if($errors->any())<div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><p class="font-semibold">This action needs attention.</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"><div><div class="flex flex-wrap items-center gap-2"><h2 class="text-xl font-semibold">{{ $transfer->transfer_number }}</h2><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $badge }}">{{ str($transfer->status)->replace('_',' ')->headline() }}</span></div><p class="mt-2 text-sm text-slate-500">Requested by {{ $transfer->requester?->name }} on {{ $transfer->created_at->format('d M Y, h:i A') }}</p></div><div class="flex flex-wrap gap-2"><a target="_blank" href="{{ route('inventory.transfers.print', $transfer) }}" class="inline-flex min-h-11 items-center rounded-lg border border-slate-300 px-4 text-sm font-semibold">Print document</a>@if(!$terminal && !in_array($transfer->status,['in_transit','partially_received','discrepancy','received']))
            @can('inventory.transfers.cancel')<button form="cancel-transfer" class="min-h-11 rounded-lg border border-rose-200 px-4 text-sm font-semibold text-rose-700">Cancel</button>@endcan
            @endif</div></div>
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"><div><p class="text-xs font-semibold uppercase text-slate-400">From</p><p class="mt-1 font-semibold">{{ $transfer->sourceWarehouse?->name }}</p><p class="text-sm text-slate-500">{{ $transfer->sourceLocation?->name ?? 'Main stock area' }}</p></div><div><p class="text-xs font-semibold uppercase text-slate-400">To</p><p class="mt-1 font-semibold">{{ $transfer->destinationWarehouse?->name }}</p><p class="text-sm text-slate-500">{{ $transfer->destinationLocation?->name ?? 'Main stock area' }}</p></div><div><p class="text-xs font-semibold uppercase text-slate-400">Expected arrival</p><p class="mt-1 font-semibold">{{ $transfer->expected_arrival_at?->format('d M Y, h:i A') ?? 'Not set' }}</p></div><div><p class="text-xs font-semibold uppercase text-slate-400">Total quantity</p><p class="mt-1 font-semibold">{{ number_format((float)$transfer->items->sum('requested_quantity'), 3) }}</p><p class="text-sm text-slate-500">{{ $transfer->items->count() }} SKUs</p></div></div>
        @if(!$terminal)<div class="mt-7 grid grid-cols-4 gap-1 sm:grid-cols-7">@foreach($statuses as $index => $status)<div class="min-w-0 text-center"><div class="mx-auto h-2 rounded-full {{ $current !== false && $index <= $current ? 'bg-teal-500' : 'bg-slate-200 dark:bg-slate-700' }}"></div><p class="mt-2 truncate text-[10px] font-medium text-slate-500 sm:text-xs">{{ str($status)->replace('_',' ')->headline() }}</p></div>@endforeach</div>@endif
    </section>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="font-semibold">Products</h3></div>
        <div class="hidden overflow-x-auto md:block"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-950"><tr><th class="px-5 py-3">Product</th><th class="px-5 py-3 text-right">Requested</th><th class="px-5 py-3 text-right">Approved</th><th class="px-5 py-3 text-right">Packed</th><th class="px-5 py-3 text-right">Dispatched</th><th class="px-5 py-3 text-right">Received</th><th class="px-5 py-3 text-right">In transit</th></tr></thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@foreach($transfer->items as $item)<tr><td class="px-5 py-4"><p class="font-semibold">{{ $item->product->name }}</p><p class="text-xs text-slate-500">{{ $item->product->sku }} · {{ $item->unit_snapshot }}</p></td>@foreach(['requested_quantity','approved_quantity','packed_quantity','dispatched_quantity','received_quantity','in_transit_quantity'] as $field)<td class="px-5 py-4 text-right font-medium">{{ $item->{$field} }}</td>@endforeach</tr>@endforeach</tbody></table></div>
        <div class="divide-y divide-slate-100 md:hidden dark:divide-slate-800">@foreach($transfer->items as $item)<div class="p-4"><div class="flex justify-between gap-3"><div><p class="font-semibold">{{ $item->product->name }}</p><p class="text-xs text-slate-500">{{ $item->product->sku }}</p></div><p class="font-semibold">{{ $item->requested_quantity }} {{ $item->unit_snapshot }}</p></div><div class="mt-3 grid grid-cols-3 gap-2 text-xs"><div><span class="text-slate-400">Packed</span><p class="font-semibold">{{ $item->packed_quantity }}</p></div><div><span class="text-slate-400">Received</span><p class="font-semibold">{{ $item->received_quantity }}</p></div><div><span class="text-slate-400">In transit</span><p class="font-semibold">{{ $item->in_transit_quantity }}</p></div></div></div>@endforeach</div>
    </section>

    @if($transfer->status === 'draft')
        @can('inventory.transfers.create')<section class="rounded-lg border border-teal-200 bg-teal-50 p-5 dark:border-teal-900 dark:bg-teal-950/30"><h3 class="font-semibold">Ready to request this transfer?</h3><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Submitting sends it for approval when your company requires approval.</p><form method="POST" action="{{ route('inventory.transfers.submit',$transfer) }}" class="mt-4">@csrf<button class="min-h-11 rounded-lg bg-teal-700 px-5 text-sm font-semibold text-white">Submit transfer</button></form></section>@endcan
    @elseif(in_array($transfer->status,['requested','pending_approval']))
        @can('inventory.transfers.approve')<section class="grid gap-5 lg:grid-cols-2"><form method="POST" action="{{ route('inventory.transfers.approve',$transfer) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">@csrf<h3 class="font-semibold">Approve quantities</h3><p class="mt-1 text-sm text-slate-500">You may approve less than requested. Explain material changes in the note.</p><div class="mt-4 space-y-3">@foreach($transfer->items as $index=>$item)<input type="hidden" name="items[{{$index}}][id]" value="{{$item->id}}"><label class="grid grid-cols-[1fr_120px] items-center gap-3 text-sm"><span>{{ $item->product->name }} <small class="block text-slate-500">Requested {{ $item->requested_quantity }}</small></span><input type="number" name="items[{{$index}}][approved_quantity]" value="{{$item->requested_quantity}}" min="0" max="{{$item->requested_quantity}}" step="0.001" class="min-h-11 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>@endforeach</div><textarea name="notes" rows="2" placeholder="Approval note" class="mt-4 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></textarea><button class="mt-4 min-h-11 rounded-lg bg-teal-700 px-5 text-sm font-semibold text-white">Approve transfer</button></form><form method="POST" action="{{ route('inventory.transfers.reject',$transfer) }}" class="rounded-lg border border-rose-200 bg-rose-50 p-5 dark:border-rose-900 dark:bg-rose-950/30">@csrf<h3 class="font-semibold">Reject transfer</h3><p class="mt-1 text-sm text-slate-500">A reason is required and will remain in the audit trail.</p><textarea required name="reason" rows="3" class="mt-4 w-full rounded-lg border-rose-200 dark:bg-slate-950" placeholder="Reason for rejection"></textarea><button class="mt-4 min-h-11 rounded-lg border border-rose-300 px-5 text-sm font-semibold text-rose-700">Reject</button></form></section>@endcan
    @elseif(in_array($transfer->status,['approved','packing']))
        @can('inventory.transfers.pack')<form method="POST" action="{{ route('inventory.transfers.pack',$transfer) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">@csrf<h3 class="font-semibold">Pack products</h3><p class="mt-1 text-sm text-slate-500">Confirm packed quantities before dispatch. Packing does not move stock.</p><div class="mt-4 grid gap-3">@foreach($transfer->items as $index=>$item)<input type="hidden" name="items[{{$index}}][id]" value="{{$item->id}}"><label class="grid grid-cols-[1fr_130px] items-center gap-3 text-sm"><span>{{ $item->product->name }}<small class="block text-slate-500">Approved {{ $item->approved_quantity }}</small></span><input type="number" name="items[{{$index}}][packed_quantity]" value="{{ (float)$item->packed_quantity > 0 ? $item->packed_quantity : $item->approved_quantity }}" min="0" max="{{$item->approved_quantity}}" step="0.001" class="min-h-11 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>@endforeach</div><button class="mt-4 min-h-11 rounded-lg bg-slate-950 px-5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save packing</button></form>@endcan
        @can('inventory.transfers.dispatch')<form method="POST" action="{{ route('inventory.transfers.dispatch',$transfer) }}" class="rounded-lg border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-900 dark:bg-indigo-950/30">@csrf<h3 class="font-semibold">Dispatch packed stock</h3><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">This immediately removes saleable stock from {{ $transfer->sourceWarehouse?->name }} and places it in transit.</p><button class="mt-4 min-h-11 rounded-lg bg-indigo-700 px-5 text-sm font-semibold text-white" onclick="return confirm('Dispatch this stock now? Source stock will decrease immediately.')">Dispatch stock</button></form>@endcan
    @elseif(in_array($transfer->status,['in_transit','partially_received','discrepancy']))
        @can('inventory.transfers.receive')<form method="POST" action="{{ route('inventory.transfers.receive',$transfer) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">@csrf<input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}"><h3 class="font-semibold">Receive incoming stock</h3><p class="mt-1 text-sm text-slate-500">Enter only what you are confirming now. Unreceived quantities remain in transit.</p><div class="mt-5 space-y-4">@foreach($transfer->items->where('in_transit_quantity','>',0) as $index=>$item)<div class="rounded-lg border border-slate-200 p-4 dark:border-slate-700"><input type="hidden" name="items[{{$index}}][id]" value="{{$item->id}}"><div class="flex justify-between gap-3"><div><p class="font-semibold">{{ $item->product->name }}</p><p class="text-xs text-slate-500">{{ $item->product->sku }}</p></div><div class="text-right"><p class="text-xs text-slate-500">Still in transit</p><p class="font-semibold">{{ $item->in_transit_quantity }}</p></div></div><div class="mt-4 grid grid-cols-3 gap-3"><label class="text-xs font-semibold text-slate-500">Received<input name="items[{{$index}}][received_quantity]" value="0" type="number" min="0" max="{{$item->in_transit_quantity}}" step="0.001" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-base text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label><label class="text-xs font-semibold text-slate-500">Damaged<input name="items[{{$index}}][damaged_quantity]" value="0" type="number" min="0" max="{{$item->in_transit_quantity}}" step="0.001" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-base text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label><label class="text-xs font-semibold text-slate-500">Short<input name="items[{{$index}}][short_quantity]" value="0" type="number" min="0" max="{{$item->in_transit_quantity}}" step="0.001" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-base text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label></div><input name="items[{{$index}}][notes]" placeholder="Note for damage or shortage" class="mt-3 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></div>@endforeach</div><textarea name="notes" rows="2" placeholder="Receiving note" class="mt-4 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></textarea><button class="mt-4 min-h-12 w-full rounded-lg bg-teal-700 px-5 font-semibold text-white sm:w-auto">Record receipt</button></form>@endcan
    @endif

    @if(in_array($transfer->status,['in_transit','partially_received','discrepancy'],true))
        @can('inventory.transfers.receive')<form method="POST" action="{{route('inventory.transfers.discrepancies.store',$transfer)}}" class="rounded-lg border border-amber-200 bg-amber-50 p-5 dark:border-amber-900 dark:bg-amber-950/20">@csrf<h3 class="font-semibold">Report another receiving issue</h3><p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Use this for a wrong item, excess item, missing package, or another exception. Reporting does not change stock.</p><div class="mt-4 grid gap-3 md:grid-cols-3"><select required name="item_id" class="min-h-11 rounded-lg border-amber-200 dark:bg-slate-950"><option value="">Choose product</option>@foreach($transfer->items as $item)<option value="{{$item->id}}">{{$item->product->name}}</option>@endforeach</select><select required name="type" class="min-h-11 rounded-lg border-amber-200 dark:bg-slate-950"><option value="">Issue type</option>@foreach(['wrong_item'=>'Wrong item','excess_received'=>'Excess received','missing_package'=>'Missing package','short_received'=>'Short received','damaged_in_transit'=>'Damaged in transit','other'=>'Other'] as $value=>$label)<option value="{{$value}}">{{$label}}</option>@endforeach</select><input required name="discrepancy_quantity" type="number" min="0.001" step="0.001" placeholder="Quantity affected" class="min-h-11 rounded-lg border-amber-200 dark:bg-slate-950"><input required name="reason" maxlength="255" placeholder="Short reason" class="min-h-11 rounded-lg border-amber-200 dark:bg-slate-950 md:col-span-2"><input name="notes" placeholder="Additional notes" class="min-h-11 rounded-lg border-amber-200 dark:bg-slate-950"><button class="min-h-11 rounded-lg bg-amber-700 px-4 text-sm font-semibold text-white md:w-fit">Record issue</button></div></form>@endcan
    @endif

    @if($transfer->discrepancies->isNotEmpty())<section class="rounded-lg border border-rose-200 bg-white p-5 shadow-sm dark:border-rose-900 dark:bg-slate-900"><h3 class="font-semibold">Transfer discrepancies</h3><div class="mt-4 space-y-4">@foreach($transfer->discrepancies as $discrepancy)<div class="rounded-lg bg-rose-50 p-4 dark:bg-rose-950/30"><div class="flex flex-wrap justify-between gap-2"><div><p class="font-semibold">{{ $discrepancy->transferItem?->product?->name }}</p><p class="text-sm text-rose-700 dark:text-rose-300">{{ str($discrepancy->type)->replace('_',' ')->headline() }}: {{ $discrepancy->discrepancy_quantity }}</p>@if($discrepancy->reason)<p class="mt-1 text-xs text-slate-500">{{$discrepancy->reason}}</p>@endif</div><span class="rounded-full bg-white px-2 py-1 text-xs font-semibold dark:bg-slate-900">{{ ucfirst($discrepancy->status) }}</span></div>@if($discrepancy->status==='open')
        @can('inventory.transfers.resolve_discrepancy')<form method="POST" action="{{ route('inventory.transfers.discrepancies.resolve',$discrepancy) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">@csrf<select required name="resolution" class="min-h-11 rounded-lg border-rose-200 dark:bg-slate-950"><option value="">Choose resolution</option><option value="confirm_loss">Confirm loss</option>@if($discrepancy->type==='short_received')<option value="restock_source">Return to source stock</option><option value="add_destination_damaged">Add as damaged stock</option>@endif<option value="manager_adjustment">Handled by manager adjustment</option></select><input name="notes" placeholder="Resolution note" class="min-h-11 rounded-lg border-rose-200 dark:bg-slate-950"><button class="min-h-11 rounded-lg bg-rose-700 px-4 text-sm font-semibold text-white">Resolve</button></form>@endcan
        @else<p class="mt-2 text-sm text-slate-500">Resolved as {{ str($discrepancy->resolution)->replace('_',' ') }} by {{ $discrepancy->resolver?->name }}.</p>@endif</div>@endforeach</div></section>@endif

    @if(!in_array($transfer->status,['in_transit','partially_received','discrepancy','received','rejected','cancelled']))<form id="cancel-transfer" method="POST" action="{{ route('inventory.transfers.cancel',$transfer) }}" class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">@csrf<label class="text-sm font-semibold">Cancellation reason<input required name="reason" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950" placeholder="Why is this transfer being cancelled?"></label></form>@endif
</div>
@if(in_array($transfer->status, ['approved', 'packing', 'in_transit', 'partially_received', 'discrepancy'], true))
<script>
document.addEventListener('DOMContentLoaded', () => {
    const workflowItems = @json($workflowItems);
    const stages = [
        { form: document.querySelector('form[action$="/pack"]'), field: 'packed_quantity', limit: 'approved', label: 'Scan products while packing' },
        { form: document.querySelector('form[action$="/receive"]'), field: 'received_quantity', limit: 'remaining', label: 'Scan products while receiving' },
    ];

    stages.forEach(stage => {
        if (!stage.form) return;
        const wrapper = document.createElement('label');
        wrapper.className = 'mt-4 block text-sm font-semibold';
        wrapper.textContent = stage.label;
        const input = document.createElement('input');
        input.type = 'search';
        input.autocomplete = 'off';
        input.placeholder = 'Scan barcode or enter SKU';
        input.className = 'mt-2 min-h-12 w-full rounded-lg border-slate-300 text-base dark:border-slate-700 dark:bg-slate-950';
        const feedback = document.createElement('span');
        feedback.className = 'mt-1 block text-xs font-normal text-slate-500';
        feedback.textContent = 'Each successful scan adds one unit.';
        wrapper.append(input, feedback);
        stage.form.querySelector('p')?.after(wrapper);

        input.addEventListener('keydown', event => {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            const code = input.value.trim().toLowerCase();
            const item = workflowItems.find(row => [row.barcode, row.sku].filter(Boolean).some(value => String(value).toLowerCase() === code));
            if (!item) {
                feedback.textContent = 'This product is not part of the transfer.';
                feedback.className = 'mt-1 block text-xs font-normal text-rose-600';
                input.select();
                return;
            }
            const quantity = stage.form.querySelector(`[name="items[${item.index}][${stage.field}]"]`);
            const maximum = Number(item[stage.limit]);
            if (!quantity || Number(quantity.value || 0) >= maximum) {
                feedback.textContent = `${item.name} is already at its allowed quantity.`;
                feedback.className = 'mt-1 block text-xs font-normal text-amber-600';
                input.select();
                return;
            }
            quantity.value = Math.min(maximum, Number(quantity.value || 0) + 1);
            quantity.dispatchEvent(new Event('change', { bubbles: true }));
            feedback.textContent = `${item.name} added. Quantity is now ${quantity.value}.`;
            feedback.className = 'mt-1 block text-xs font-normal text-emerald-600';
            input.value = '';
        });
    });
});
</script>
@endif
@if(in_array($transfer->status, ['in_transit', 'partially_received', 'discrepancy'], true))
<script>
document.addEventListener('DOMContentLoaded', () => {
    const trackedItems = @json($trackedItems);

    trackedItems.filter(item => item.tracked && item.serials.length).forEach(item => {
        const notes = document.querySelector(`[name="items[${item.index}][notes]"]`);
        if (!notes) return;

        const label = document.createElement('label');
        label.className = 'mt-3 block text-xs font-semibold text-slate-500';
        label.textContent = 'Serial numbers being received';
        const select = document.createElement('select');
        select.multiple = true;
        select.name = `items[${item.index}][serial_ids][]`;
        select.className = 'mt-1 min-h-28 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950';
        item.serials.forEach(serial => select.add(new Option(serial.number, serial.id)));
        const help = document.createElement('span');
        help.className = 'mt-1 block font-normal';
        help.textContent = 'Select one serial for each usable or damaged unit recorded above.';
        label.append(select, help);
        notes.before(label);
    });
});
</script>
@endif
@endsection

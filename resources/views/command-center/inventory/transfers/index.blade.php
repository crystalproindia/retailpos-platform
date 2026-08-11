@extends('layouts.admin')
@section('title', 'Move Stock')
@section('page-title', 'Move Stock')
@section('breadcrumbs')<span>/</span><a href="{{ route('inventory.dashboard') }}">Inventory</a><span>/</span><span>Transfers</span>@endsection
@section('content')
@include('command-center.inventory.partials.nav')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div><h2 class="text-xl font-semibold text-slate-950 dark:text-white">Stock transfers</h2><p class="mt-1 text-sm text-slate-500">Move products between stores and warehouses without double-counting stock.</p></div>
        @can('inventory.transfers.create')<a href="{{ route('inventory.transfers.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-slate-950 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus-visible:ring-2 focus-visible:ring-teal-500 dark:bg-teal-300 dark:text-slate-950">Move stock</a>@endcan
    </div>

    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        @foreach ([['Draft', $metrics['draft']], ['Awaiting approval', $metrics['awaiting']], ['Approved', $metrics['approved']], ['Packing', $metrics['packing']], ['Ready to dispatch', $metrics['ready']], ['In transit', $metrics['transit']], ['Partially received', $metrics['partial']], ['Discrepancies', $metrics['discrepancy']], ['Overdue', $metrics['overdue']], ['Completed today', $metrics['completed_today']]] as [$label, $value])
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-medium text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $value }}</p></div>
        @endforeach
    </div>

    <form method="GET" class="grid gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:grid-cols-2 xl:grid-cols-4 dark:border-slate-800 dark:bg-slate-900">
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Transfer number<input name="q" value="{{ request('q') }}" placeholder="Search transfer" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">From<select name="source" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All locations</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) request('source') === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">To<select name="destination" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All locations</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) request('destination') === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Status<select name="status" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All statuses</option>@foreach(['draft','pending_approval','approved','packing','in_transit','partially_received','discrepancy','received','rejected','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_',' ')->headline() }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Product<select name="product" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) request('product') === (string) $product->id)>{{ $product->name }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">Requested by<select name="requested_by" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Anyone</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected((string) request('requested_by') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">From date<input type="date" name="date_from" value="{{ request('date_from') }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
        <label class="text-xs font-semibold text-slate-600 dark:text-slate-300">To date<input type="date" name="date_to" value="{{ request('date_to') }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
        <div class="flex items-end gap-2"><button class="min-h-11 flex-1 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white dark:bg-slate-100 dark:text-slate-950">Filter</button><a href="{{ route('inventory.transfers.index') }}" class="inline-flex min-h-11 items-center px-2 text-sm font-semibold text-slate-500">Clear</a></div>
    </form>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-950"><tr><th class="px-5 py-3">Transfer</th><th class="px-5 py-3">From / To</th><th class="px-5 py-3">Progress</th><th class="px-5 py-3">Requested</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($transfers as $transfer)
                        @php $total = (float) $transfer->items->sum('dispatched_quantity'); $received = (float) $transfer->items->sum('received_quantity') + (float) $transfer->items->sum('damaged_quantity'); $percent = $total > 0 ? min(100, (int) round($received / $total * 100)) : 0; @endphp
                        <tr class="transition hover:bg-slate-50 dark:hover:bg-slate-800/50"><td class="px-5 py-4"><p class="font-semibold">{{ $transfer->transfer_number }}</p><span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ str($transfer->status)->replace('_',' ')->headline() }}</span></td><td class="px-5 py-4"><p class="font-medium">{{ $transfer->sourceWarehouse?->name }}</p><p class="text-slate-500">to {{ $transfer->destinationWarehouse?->name }}</p></td><td class="px-5 py-4"><div class="h-2 w-32 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full bg-teal-500" style="width: {{ $percent }}%"></div></div><p class="mt-1 text-xs text-slate-500">{{ $percent }}% received</p></td><td class="px-5 py-4 text-slate-500">{{ $transfer->created_at->format('d M Y') }}<br>{{ $transfer->requester?->name }}</td><td class="px-5 py-4 text-right"><a href="{{ route('inventory.transfers.show', $transfer) }}" class="inline-flex min-h-11 items-center font-semibold text-teal-700 dark:text-teal-300">Open</a></td></tr>
                    @empty<tr><td colspan="5" class="px-6 py-14 text-center"><p class="font-semibold text-slate-800 dark:text-slate-100">No transfers found</p><p class="mt-1 text-sm text-slate-500">Create a transfer when stock needs to move between locations.</p></td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="divide-y divide-slate-100 md:hidden dark:divide-slate-800">
            @forelse($transfers as $transfer)<a href="{{ route('inventory.transfers.show', $transfer) }}" class="block p-4 transition active:bg-slate-50 dark:active:bg-slate-800"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold">{{ $transfer->transfer_number }}</p><p class="mt-1 text-sm text-slate-500">{{ $transfer->sourceWarehouse?->name }} to {{ $transfer->destinationWarehouse?->name }}</p></div><span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold dark:bg-slate-800">{{ str($transfer->status)->replace('_',' ')->headline() }}</span></div><p class="mt-3 text-xs text-slate-500">{{ $transfer->items->count() }} SKUs · {{ $transfer->created_at->format('d M Y') }}</p></a>@empty<div class="p-10 text-center text-sm text-slate-500">No transfers found.</div>@endforelse
        </div>
        @if($transfers->hasPages())<div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $transfers->links() }}</div>@endif
    </section>
</div>
@endsection

@extends('layouts.admin')
@section('title', 'Stock Transfers')
@section('page-title', 'Stock Transfers')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <h2 class="text-base font-semibold text-slate-950 dark:text-white">Create stock transfer</h2>
        <p class="mt-1 text-sm text-slate-500">Drafts do not change stock. Dispatch reduces the source; receipt adds it to the destination.</p>

        @if ($errors->any())
            <div role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-200">
                <p class="font-semibold">Please review the transfer details.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('inventory.transfers.store') }}" class="mt-5 grid gap-4 md:grid-cols-2">
            @csrf
            <label class="text-sm font-medium">
                From outlet
                <select name="source_branch_id" required class="mt-1 min-h-11 w-full rounded-lg border-slate-300 focus-visible:ring-2 focus-visible:ring-teal-500 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected((string) old('source_branch_id') === (string) $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">
                To outlet
                <select name="destination_branch_id" required aria-invalid="{{ $errors->has('destination_branch_id') ? 'true' : 'false' }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 focus-visible:ring-2 focus-visible:ring-teal-500 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($outlets as $outlet)
                        <option value="{{ $outlet->id }}" @selected((string) old('destination_branch_id') === (string) $outlet->id)>{{ $outlet->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">
                Product
                <select name="items[0][product_id]" required class="mt-1 min-h-11 w-full rounded-lg border-slate-300 focus-visible:ring-2 focus-visible:ring-teal-500 dark:border-slate-700 dark:bg-slate-950">
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected((string) old('items.0.product_id') === (string) $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-medium">
                Quantity
                <input name="items[0][quantity]" value="{{ old('items.0.quantity') }}" required type="number" min="0.001" step="0.001" aria-invalid="{{ $errors->has('items.0.quantity') || $errors->has('items') ? 'true' : 'false' }}" class="mt-1 min-h-11 w-full rounded-lg border-slate-300 focus-visible:ring-2 focus-visible:ring-teal-500 dark:border-slate-700 dark:bg-slate-950">
            </label>
            <label class="text-sm font-medium md:col-span-2">
                Notes
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border-slate-300 focus-visible:ring-2 focus-visible:ring-teal-500 dark:border-slate-700 dark:bg-slate-950">{{ old('notes') }}</textarea>
            </label>
            <div class="md:col-span-2">
                <button class="min-h-11 rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 dark:bg-teal-300 dark:text-slate-950">Save draft transfer</button>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="border-b border-slate-200 px-6 py-4 dark:border-slate-800">
            <h2 class="font-semibold">Recent transfers</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-950">
                    <tr>
                        <th class="px-6 py-3">Transfer</th>
                        <th class="px-6 py-3">Route</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($transfers as $transfer)
                        <tr>
                            <td class="px-6 py-4 font-medium">{{ $transfer->transfer_number }}</td>
                            <td class="px-6 py-4 text-slate-500">{{ $transfer->sourceOutlet->name }} → {{ $transfer->destinationOutlet->name }}</td>
                            <td class="px-6 py-4"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold dark:bg-slate-800">{{ str($transfer->status)->replace('_', ' ')->headline() }}</span></td>
                            <td class="px-6 py-4">
                                @if ($transfer->status === 'draft')
                                    <form method="POST" action="{{ route('inventory.transfers.dispatch', $transfer) }}">
                                        @csrf
                                        <button class="min-h-11 font-semibold text-teal-700 focus-visible:ring-2 focus-visible:ring-teal-500 dark:text-teal-300">Dispatch</button>
                                    </form>
                                @elseif ($transfer->status === 'in_transit')
                                    <form method="POST" action="{{ route('inventory.transfers.receive', $transfer) }}">
                                        @csrf
                                        <button class="min-h-11 font-semibold text-teal-700 focus-visible:ring-2 focus-visible:ring-teal-500 dark:text-teal-300">Receive</button>
                                    </form>
                                @else
                                    <span class="text-slate-500">Complete</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-10 text-center text-slate-500">No transfers yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

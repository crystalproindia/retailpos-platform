@extends('layouts.admin')

@section('title', 'Purchase Requests')
@section('page-title', 'Purchase Requests')
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>Requests</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <form method="GET" class="flex flex-1 flex-col gap-2 sm:flex-row">
            <input name="search" value="{{ request('search') }}" placeholder="Search request number" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            <select name="status" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                <option value="">All statuses</option>
                @foreach (['draft', 'pending_review', 'approved', 'rejected', 'converted_to_po', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                @endforeach
            </select>
            <button class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Filter</button>
        </form>
        <a href="{{ route('purchases.requests.create') }}" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">New request</a>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr><th class="px-5 py-3">Request</th><th class="px-5 py-3">Warehouse</th><th class="px-5 py-3">Priority</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Items</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($requests as $purchaseRequest)
                        <tr>
                            <td class="px-5 py-3 font-medium"><a href="{{ route('purchases.requests.show', $purchaseRequest) }}">{{ $purchaseRequest->request_number }}</a></td>
                            <td class="px-5 py-3 text-slate-500">{{ $purchaseRequest->warehouse?->name ?: 'Any warehouse' }}</td>
                            <td class="px-5 py-3">{{ str($purchaseRequest->priority->value)->headline() }}</td>
                            <td class="px-5 py-3">{{ str($purchaseRequest->status->value)->headline() }}</td>
                            <td class="px-5 py-3 text-right">{{ $purchaseRequest->items->count() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No purchase requests found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $requests->links() }}</div>
    </section>
@endsection

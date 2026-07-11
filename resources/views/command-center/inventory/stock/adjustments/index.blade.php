@extends('layouts.admin')

@section('title', 'Stock Adjustments')
@section('page-title', 'Stock Adjustments')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Adjustments</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <div class="mb-4 flex justify-end"><a href="{{ route('inventory.adjustments.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">New adjustment</a></div>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400"><tr><th class="px-5 py-3">Number</th><th class="px-5 py-3">Warehouse</th><th class="px-5 py-3">Reason</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($adjustments as $adjustment)
                    <tr><td class="px-5 py-3 font-mono text-xs">{{ $adjustment->adjustment_number }}</td><td class="px-5 py-3">{{ $adjustment->warehouse?->name }}</td><td class="px-5 py-3">{{ $adjustment->reason }}</td><td class="px-5 py-3">{{ str($adjustment->status)->headline() }}</td><td class="px-5 py-3 text-right"><a href="{{ route('inventory.adjustments.show', $adjustment) }}" class="text-sm font-semibold text-teal-700">Open</a></td></tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No stock adjustments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $adjustments->links() }}</div>
    </div>
@endsection

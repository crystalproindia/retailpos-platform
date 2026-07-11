@extends('layouts.admin')

@section('title', 'Barcode Print Batches')
@section('page-title', 'Barcode Print Batches')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Barcode Batches</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <div class="mb-4 flex justify-end"><a href="{{ route('inventory.barcode-batches.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">New batch</a></div>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400"><tr><th class="px-5 py-3">Batch</th><th class="px-5 py-3">Template</th><th class="px-5 py-3 text-right">Labels</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">@forelse($batches as $batch)<tr><td class="px-5 py-3 font-mono text-xs">{{ $batch->batch_number }}</td><td class="px-5 py-3">{{ $batch->template?->name }}</td><td class="px-5 py-3 text-right">{{ $batch->total_labels }}</td><td class="px-5 py-3">{{ str($batch->status)->headline() }}</td><td class="px-5 py-3 text-right"><a href="{{ route('inventory.barcode-batches.show', $batch) }}" class="text-sm font-semibold text-teal-700">Preview</a></td></tr>@empty<tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No print batches yet.</td></tr>@endforelse</tbody>
        </table>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $batches->links() }}</div>
    </div>
@endsection

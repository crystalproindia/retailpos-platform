@extends('layouts.admin')

@section('title', 'Stock Locations')
@section('page-title', 'Stock Locations')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Locations</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <div class="mb-4 flex justify-end"><a href="{{ route('inventory.locations.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">New location</a></div>
    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400"><tr><th class="px-5 py-3">Location</th><th class="px-5 py-3">Warehouse</th><th class="px-5 py-3">Type</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($locations as $location)
                    <tr><td class="px-5 py-3"><p class="font-semibold">{{ $location->name }}</p><p class="font-mono text-xs text-slate-500">{{ $location->code }}</p></td><td class="px-5 py-3">{{ $location->warehouse?->name }}</td><td class="px-5 py-3">{{ str($location->type)->headline() }}</td><td class="px-5 py-3 text-right"><a href="{{ route('inventory.locations.edit', $location) }}" class="text-sm font-semibold text-slate-700 hover:text-teal-700">Edit</a></td></tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-8 text-center text-slate-500">No stock locations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $locations->links() }}</div>
    </div>
@endsection

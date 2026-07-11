@extends('layouts.admin')

@section('title', 'Warehouses')
@section('page-title', 'Warehouses')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Warehouses</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')

    <div class="mb-4 flex justify-end">
        <a href="{{ route('inventory.warehouses.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm">New warehouse</a>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                <tr>
                    <th class="px-5 py-3">Warehouse</th>
                    <th class="px-5 py-3">Branch</th>
                    <th class="px-5 py-3">Type</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($warehouses as $warehouse)
                    <tr>
                        <td class="px-5 py-3">
                            <p class="font-semibold">{{ $warehouse->name }}</p>
                            <p class="font-mono text-xs text-slate-500">{{ $warehouse->code }}</p>
                        </td>
                        <td class="px-5 py-3 text-slate-500">{{ $warehouse->branch?->name ?? 'Company wide' }}</td>
                        <td class="px-5 py-3">{{ str($warehouse->type)->headline() }}</td>
                        <td class="px-5 py-3">{{ $warehouse->deleted_at ? 'Trashed' : ($warehouse->is_active ? 'Active' : 'Inactive') }}</td>
                        <td class="px-5 py-3 text-right">
                            @if ($warehouse->deleted_at)
                                <form method="POST" action="{{ route('inventory.warehouses.restore', $warehouse) }}">@csrf<button class="text-sm font-semibold text-teal-700">Restore</button></form>
                            @else
                                <a href="{{ route('inventory.warehouses.edit', $warehouse) }}" class="text-sm font-semibold text-slate-700 hover:text-teal-700">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No warehouses yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $warehouses->links() }}</div>
    </div>
@endsection

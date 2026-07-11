@extends('layouts.admin')

@section('title', $title)
@section('page-title', $title)
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>{{ $title }}</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')

    <div class="mb-4 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $items->total() }} records</p>
        <a href="{{ route($routePrefix.'.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-700">New</a>
    </div>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr>
                        <th class="px-5 py-3">Name</th>
                        @foreach ($fields as $field)
                            <th class="px-5 py-3">{{ str($field)->replace('_', ' ')->headline() }}</th>
                        @endforeach
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-5 py-3 font-semibold">{{ $item->name }}</td>
                            @foreach ($fields as $field)
                                <td class="px-5 py-3 text-slate-500">{{ $item->{$field} }}</td>
                            @endforeach
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $item->deleted_at ? 'bg-rose-100 text-rose-700' : ($item->is_active ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $item->deleted_at ? 'Trashed' : ($item->is_active ? 'Active' : 'Inactive') }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="inline-flex items-center gap-2">
                                    @if ($item->deleted_at)
                                        <form method="POST" action="{{ route($routePrefix.'.restore', $item->id) }}">
                                            @csrf
                                            <button class="text-sm font-semibold text-teal-700">Restore</button>
                                        </form>
                                    @else
                                        <a href="{{ route($routePrefix.'.edit', $item->id) }}" class="text-sm font-semibold text-slate-700 hover:text-teal-700 dark:text-slate-300">Edit</a>
                                        <form method="POST" action="{{ route($routePrefix.'.destroy', $item->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-sm font-semibold text-rose-600">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($fields) + 3 }}" class="px-5 py-8 text-center text-slate-500">No records found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $items->links() }}</div>
    </div>
@endsection

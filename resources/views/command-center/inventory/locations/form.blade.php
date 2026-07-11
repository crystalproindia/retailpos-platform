@extends('layouts.admin')

@section('title', $location->exists ? 'Edit Location' : 'New Location')
@section('page-title', $location->exists ? 'Edit Location' : 'New Location')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><a href="{{ route('inventory.locations.index') }}" class="hover:text-slate-950 dark:hover:text-white">Locations</a>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <form method="POST" action="{{ $location->exists ? route('inventory.locations.update', $location) : route('inventory.locations.store') }}" class="max-w-3xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @if ($location->exists) @method('PUT') @endif
        <div class="grid gap-4 md:grid-cols-2">
            <label class="space-y-1"><span class="text-sm font-medium">Warehouse</span><select name="warehouse_id" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $location->warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></label>
            <label class="space-y-1"><span class="text-sm font-medium">Type</span><input name="type" value="{{ old('type', $location->type ?? 'bin') }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">Name</span><input name="name" value="{{ old('name', $location->name) }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">Code</span><input name="code" value="{{ old('code', $location->code) }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            @foreach (['aisle' => 'Aisle', 'rack' => 'Rack', 'shelf' => 'Shelf', 'bin' => 'Bin'] as $field => $label)
                <label class="space-y-1"><span class="text-sm font-medium">{{ $label }}</span><input name="{{ $field }}" value="{{ old($field, $location->{$field}) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            @endforeach
            <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $location->is_active ?? true)) class="rounded border-slate-300 text-teal-600"><span>Active</span></label>
        </div>
        @if ($errors->any())<div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <div class="mt-6 flex justify-end gap-3"><a href="{{ route('inventory.locations.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</a><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save</button></div>
    </form>
@endsection

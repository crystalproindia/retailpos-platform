@extends('layouts.admin')

@section('title', $warehouse->exists ? 'Edit Warehouse' : 'New Warehouse')
@section('page-title', $warehouse->exists ? 'Edit Warehouse' : 'New Warehouse')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><a href="{{ route('inventory.warehouses.index') }}" class="hover:text-slate-950 dark:hover:text-white">Warehouses</a>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')

    <form method="POST" action="{{ $warehouse->exists ? route('inventory.warehouses.update', $warehouse) : route('inventory.warehouses.store') }}" class="max-w-4xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @if ($warehouse->exists) @method('PUT') @endif
        <div class="grid gap-4 md:grid-cols-2">
            <label class="space-y-1"><span class="text-sm font-medium">Name</span><input name="name" value="{{ old('name', $warehouse->name) }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">Code</span><input name="code" value="{{ old('code', $warehouse->code) }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">Branch</span><select name="branch_id" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Company wide</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $warehouse->branch_id) == $branch->id)>{{ $branch->name }}</option>@endforeach</select></label>
            <label class="space-y-1"><span class="text-sm font-medium">Type</span><input name="type" value="{{ old('type', $warehouse->type ?? 'store') }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1 md:col-span-2"><span class="text-sm font-medium">Address line 1</span><input name="address_line_1" value="{{ old('address_line_1', $warehouse->address_line_1) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">City</span><input name="city" value="{{ old('city', $warehouse->city) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">State</span><input name="state" value="{{ old('state', $warehouse->state) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">Country</span><input name="country" value="{{ old('country', $warehouse->country ?? 'India') }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">Phone</span><input name="phone" value="{{ old('phone', $warehouse->phone) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $warehouse->is_primary)) class="rounded border-slate-300 text-teal-600"><span>Primary warehouse</span></label>
            <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $warehouse->is_active ?? true)) class="rounded border-slate-300 text-teal-600"><span>Active</span></label>
        </div>
        @if ($errors->any())<div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <div class="mt-6 flex justify-end gap-3"><a href="{{ route('inventory.warehouses.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Cancel</a><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save</button></div>
    </form>
@endsection

@extends('layouts.admin')

@section('title', $channel->exists ? 'Edit Channel' : 'New Channel')
@section('page-title', $channel->exists ? 'Edit Channel' : 'New Channel')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><a href="{{ route('inventory.channels.index') }}" class="hover:text-slate-950 dark:hover:text-white">Channels</a>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <form method="POST" action="{{ $channel->exists ? route('inventory.channels.update', $channel) : route('inventory.channels.store') }}" class="max-w-3xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @if($channel->exists) @method('PUT') @endif
        <div class="grid gap-4 md:grid-cols-2">
            <label class="space-y-1"><span class="text-sm font-medium">Name</span><input name="name" value="{{ old('name', $channel->name) }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">Code</span><input name="code" value="{{ old('code', $channel->code) }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">Type</span><select name="type" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach(['store','website','marketplace','social','other'] as $type)<option value="{{ $type }}" @selected(old('type', $channel->type) === $type)>{{ str($type)->headline() }}</option>@endforeach</select></label>
            <label class="space-y-1"><span class="text-sm font-medium">Sort order</span><input name="sort_order" type="number" value="{{ old('sort_order', $channel->sort_order ?? 0) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">Price strategy</span><input name="price_strategy" value="{{ old('price_strategy', $channel->price_strategy ?? 'selling_price') }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1"><span class="text-sm font-medium">Stock strategy</span><input name="stock_strategy" value="{{ old('stock_strategy', $channel->stock_strategy ?? 'available_stock') }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            <label class="space-y-1 md:col-span-2"><span class="text-sm font-medium">Description</span><textarea name="description" rows="3" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">{{ old('description', $channel->description) }}</textarea></label>
            @foreach(['is_online' => 'Online channel', 'sync_enabled' => 'Sync enabled', 'is_active' => 'Active'] as $field => $label)<label class="flex items-center gap-3 text-sm"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $channel->{$field} ?? $field === 'is_active')) class="rounded border-slate-300 text-teal-600"><span>{{ $label }}</span></label>@endforeach
        </div>
        @if ($errors->any())<div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <div class="mt-6 flex justify-end"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save channel</button></div>
    </form>
@endsection

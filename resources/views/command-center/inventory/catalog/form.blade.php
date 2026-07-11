@extends('layouts.admin')

@section('title', $title)
@section('page-title', $title)
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>{{ $title }}</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')

    <form method="POST" action="{{ $item->exists ? route($routePrefix.'.update', $item->id) : route($routePrefix.'.store') }}" class="max-w-3xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @if ($item->exists)
            @method('PUT')
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <label class="space-y-1">
                <span class="text-sm font-medium">Name</span>
                <input name="name" value="{{ old('name', $item->name) }}" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
            </label>

            @if (str_contains($routePrefix, 'categories') || str_contains($routePrefix, 'brands'))
                <label class="space-y-1">
                    <span class="text-sm font-medium">Slug</span>
                    <input name="slug" value="{{ old('slug', $item->slug) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                </label>
                <label class="space-y-1 md:col-span-2">
                    <span class="text-sm font-medium">Description</span>
                    <textarea name="description" rows="3" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">{{ old('description', $item->description) }}</textarea>
                </label>
            @endif

            @if (str_contains($routePrefix, 'categories'))
                <label class="space-y-1">
                    <span class="text-sm font-medium">Sort order</span>
                    <input name="sort_order" type="number" value="{{ old('sort_order', $item->sort_order ?? 0) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                </label>
            @endif

            @if (str_contains($routePrefix, 'units'))
                <label class="space-y-1">
                    <span class="text-sm font-medium">Short code</span>
                    <input name="short_code" value="{{ old('short_code', $item->short_code) }}" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                </label>
                <label class="space-y-1">
                    <span class="text-sm font-medium">Type</span>
                    <input name="type" value="{{ old('type', $item->type ?? 'quantity') }}" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                </label>
                <label class="space-y-1">
                    <span class="text-sm font-medium">Conversion factor</span>
                    <input name="conversion_factor" type="number" step="0.000001" value="{{ old('conversion_factor', $item->conversion_factor) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                </label>
                <label class="flex items-center gap-3 text-sm">
                    <input type="checkbox" name="decimal_allowed" value="1" @checked(old('decimal_allowed', $item->decimal_allowed)) class="rounded border-slate-300 text-teal-600">
                    <span>Decimal allowed</span>
                </label>
            @endif

            @if (str_contains($routePrefix, 'tax-rates'))
                <label class="space-y-1">
                    <span class="text-sm font-medium">Rate %</span>
                    <input name="rate" type="number" step="0.001" value="{{ old('rate', $item->rate) }}" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                </label>
                <label class="space-y-1">
                    <span class="text-sm font-medium">Tax type</span>
                    <input name="tax_type" value="{{ old('tax_type', $item->tax_type ?? 'gst') }}" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                </label>
                <label class="space-y-1">
                    <span class="text-sm font-medium">Country</span>
                    <input name="country" value="{{ old('country', $item->country ?? 'India') }}" required class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                </label>
                <label class="space-y-1">
                    <span class="text-sm font-medium">State</span>
                    <input name="state" value="{{ old('state', $item->state) }}" class="w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                </label>
                <label class="flex items-center gap-3 text-sm">
                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $item->is_default)) class="rounded border-slate-300 text-teal-600">
                    <span>Default tax rate</span>
                </label>
            @endif

            <label class="flex items-center gap-3 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true)) class="rounded border-slate-300 text-teal-600">
                <span>Active</span>
            </label>
        </div>

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route($routePrefix.'.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-300">Cancel</a>
            <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save</button>
        </div>
    </form>
@endsection

@extends('layouts.admin')

@section('title', 'Inventory Settings')
@section('page-title', 'Inventory Settings')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Settings</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <form method="POST" action="{{ route('inventory.settings.update') }}" class="max-w-3xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @method('PUT')
        @php
            $value = fn (string $key, mixed $default = null) => data_get($settings->get($key), 'value', $default);
        @endphp
        <div class="grid gap-4 md:grid-cols-2">
            <label class="space-y-1"><span class="text-sm font-medium">Default cost method</span><select name="default_cost_method" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach(['weighted_average','fifo','manual'] as $method)<option value="{{ $method }}" @selected($value('default_cost_method', 'weighted_average') === $method)>{{ str($method)->replace('_',' ')->headline() }}</option>@endforeach</select></label>
            <label class="space-y-1"><span class="text-sm font-medium">Barcode price source</span><select name="barcode_price_source" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach(['selling_price','mrp','online_price'] as $source)<option value="{{ $source }}" @selected($value('barcode_price_source', 'selling_price') === $source)>{{ str($source)->replace('_',' ')->headline() }}</option>@endforeach</select></label>
            <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="low_stock_notifications" value="1" @checked($value('low_stock_notifications', true)) class="rounded border-slate-300 text-teal-600"><span>Low stock notifications</span></label>
            <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="allow_negative_stock_default" value="1" @checked($value('allow_negative_stock_default', false)) class="rounded border-slate-300 text-teal-600"><span>Allow negative stock by default</span></label>
        </div>
        @if ($errors->any())<div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <div class="mt-6 flex justify-end"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save settings</button></div>
    </form>
@endsection

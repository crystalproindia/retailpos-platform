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
            <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="require_transfer_approval" value="1" @checked($value('require_transfer_approval', true)) class="rounded border-slate-300 text-teal-600"><span>Require manager approval for transfers</span></label>
            <label class="flex items-center gap-3 text-sm"><input type="checkbox" name="enable_transfer_packing" value="1" @checked($value('enable_transfer_packing', true)) class="rounded border-slate-300 text-teal-600"><span>Use packing step before dispatch</span></label>
            <label class="space-y-1"><span class="text-sm font-medium">Large adjustment quantity</span><input type="number" name="large_adjustment_threshold" min="0" step="0.001" value="{{$value('large_adjustment_threshold',100)}}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><span class="block text-xs text-slate-500">Advisory threshold for manager review. Every adjustment still follows approval permissions.</span></label>
        </div>
        <div class="my-6 border-t border-slate-200 dark:border-slate-800"></div>
        <div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Inventory intelligence</h2><p class="mt-1 text-sm text-slate-500">Transparent thresholds used for movement classifications and recommendations. They never post stock or create orders automatically.</p></div>
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <label class="space-y-1"><span class="text-sm font-medium">Dead stock after</span><div class="relative"><input type="number" name="dead_stock_days" min="30" max="730" value="{{ $value('dead_stock_days', 120) }}" class="w-full rounded-lg border-slate-300 pr-14 text-sm dark:border-slate-700 dark:bg-slate-950"><span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-500">days</span></div><span class="block text-xs text-slate-500">Positive stock with no qualifying sale for this period.</span></label>
            <label class="space-y-1"><span class="text-sm font-medium">New stock grace period</span><div class="relative"><input type="number" name="new_stock_grace_days" min="1" max="180" value="{{ $value('new_stock_grace_days', 30) }}" class="w-full rounded-lg border-slate-300 pr-14 text-sm dark:border-slate-700 dark:bg-slate-950"><span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-500">days</span></div><span class="block text-xs text-slate-500">Prevents newly received products being labelled slow or dead too early.</span></label>
            <label class="space-y-1"><span class="text-sm font-medium">Slow mover maximum units</span><input type="number" name="slow_mover_max_units" min="0" step="0.001" value="{{ $value('slow_mover_max_units', 2) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><span class="block text-xs text-slate-500">Maximum return-adjusted units sold in the selected velocity window.</span></label>
            <label class="space-y-1"><span class="text-sm font-medium">Fast mover minimum units</span><input type="number" name="fast_mover_min_units" min="1" step="0.001" value="{{ $value('fast_mover_min_units', 10) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><span class="block text-xs text-slate-500">Minimum return-adjusted units sold in the selected velocity window.</span></label>
            <label class="space-y-1"><span class="text-sm font-medium">Fast mover daily velocity</span><input type="number" name="fast_mover_min_daily_velocity" min="0.001" step="0.001" value="{{ $value('fast_mover_min_daily_velocity', 0.25) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><span class="block text-xs text-slate-500">Minimum average units sold per day after returns.</span></label>
            <label class="space-y-1"><span class="text-sm font-medium">Default supplier lead time</span><div class="relative"><input type="number" name="default_lead_time_days" min="1" max="365" value="{{ $value('default_lead_time_days', 7) }}" class="w-full rounded-lg border-slate-300 pr-14 text-sm dark:border-slate-700 dark:bg-slate-950"><span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-slate-500">days</span></div><span class="block text-xs text-slate-500">Used only when a stock rule has no reliable supplier lead time.</span></label>
        </div>
        @if ($errors->any())<div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
        <div class="mt-6 flex justify-end"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save settings</button></div>
    </form>
@endsection

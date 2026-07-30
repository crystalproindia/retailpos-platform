<form action="{{ $action }}" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <label class="sr-only" for="report-outlet">Outlet</label>
        <select id="report-outlet" name="outlet_id" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
            <option value="">Current outlet</option>
            @if($canViewAllOutlets)<option value="all" @selected(request('outlet_id') === 'all')>All outlets</option>@endif
            @foreach($outlets as $outlet)<option value="{{ $outlet->id }}" @selected((string) request('outlet_id') === (string) $outlet->id)>{{ $outlet->name }}</option>@endforeach
        </select>
        <label class="sr-only" for="report-warehouse">Warehouse</label>
        <select id="report-warehouse" name="warehouse_id" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
            <option value="">All warehouses</option>
            @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach
        </select>
        <label class="sr-only" for="report-date-from">From date</label>
        <input id="report-date-from" type="date" name="date_from" value="{{ request('date_from', $overview['range']['from']->toDateString()) }}" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
        <label class="sr-only" for="report-date-to">To date</label>
        <input id="report-date-to" type="date" name="date_to" value="{{ request('date_to', $overview['range']['to']->toDateString()) }}" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
        <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-teal-500/20 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Apply filters</button>
    </div>

    @if(!empty($advancedFilters))
        @php($hasAdvancedFilter = collect($advancedFilters)->contains(fn (array $filter) => filled(request($filter['name']))))
        <details class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800" @if($hasAdvancedFilter) open @endif>
            <summary class="cursor-pointer text-sm font-semibold text-slate-700 focus:outline-none focus:ring-4 focus:ring-teal-500/20 dark:text-slate-200">More filters</summary>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($advancedFilters as $filter)
                    <label class="grid gap-1 text-sm font-medium text-slate-700 dark:text-slate-200" for="report-{{ $filter['name'] }}">
                        <span>{{ $filter['label'] }}</span>
                        <select id="report-{{ $filter['name'] }}" name="{{ $filter['name'] }}" class="rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">
                            <option value="">All {{ str($filter['label'])->lower() }}</option>
                            @foreach($filter['options'] as $option)
                                <option value="{{ $option['value'] }}" @selected((string) request($filter['name']) === (string) $option['value'])>{{ $option['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                @endforeach
            </div>
        </details>
    @endif
</form>

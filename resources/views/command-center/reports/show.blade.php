@extends('layouts.admin')

@section('title', 'Report')
@section('page-title', str($report['selected_report'])->headline())

@section('content')
<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('reports.index', request()->query()) }}" class="text-sm font-semibold text-teal-700 focus:outline-none focus:ring-4 focus:ring-teal-500/20 dark:text-teal-300">Back to reports</a>
        @can('crm.reports.export')
            <a href="{{ route('reports.export', [$report['selected_report']] + request()->query()) }}" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-teal-500/20 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Export CSV</a>
        @endcan
    </div>

    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $report['scope']['label'] }} · {{ $report['range']['timezone'] }} · {{ $report['range']['from']->toDateString() }} to {{ $report['range']['to']->toDateString() }}</p>

    @include('command-center.reports.partials.filters', ['action' => route('reports.show', $report['selected_report'])])

    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="{{ str($report['selected_report'])->headline() }} summary">
        <dl class="divide-y divide-slate-100 dark:divide-slate-800">
            @foreach($report['detail'] as $key => $value)
                @if(!is_iterable($value))
                    <div class="grid gap-1 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-4">
                        <dt class="text-sm font-medium text-slate-500">{{ str($key)->replace('_', ' ')->headline() }}</dt>
                        <dd class="text-sm font-semibold text-slate-950 sm:text-right dark:text-white">{{ $reportValueFormatter->display($key, $value) }}</dd>
                    </div>
                @endif
            @endforeach
        </dl>
    </section>

    @php($tabularDetails = collect($report['detail'])->filter(fn ($value) => is_iterable($value) && collect($value)->isNotEmpty() && is_array(collect($value)->first())))
    @php($hasEmptyRows = array_key_exists('rows', $report['detail']) && collect($report['detail']['rows'])->isEmpty())

    @forelse($tabularDetails as $key => $value)
        @php($rows = collect($value))
        @php($columns = array_keys($rows->first()))
        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-labelledby="report-{{ $key }}">
            <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                <h2 id="report-{{ $key }}" class="font-semibold text-slate-950 dark:text-white">{{ str($key)->headline() }}</h2>
            </div>

            <div class="divide-y divide-slate-100 lg:hidden dark:divide-slate-800">
                @foreach($rows as $row)
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 px-4 py-3 text-sm">
                        @foreach($row as $column => $cell)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ str($column)->replace('_', ' ')->headline() }}</dt>
                                <dd class="mt-0.5 break-words text-slate-950 dark:text-white">{{ $reportValueFormatter->display($column, $cell) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endforeach
            </div>

            <div class="hidden overflow-x-auto lg:block">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 dark:bg-slate-950/50">
                        <tr>
                            @foreach($columns as $column)
                                <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">{{ str($column)->replace('_', ' ')->headline() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($rows as $row)
                            <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                @foreach($columns as $column)
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-700 dark:text-slate-200">{{ $reportValueFormatter->display($column, $row[$column] ?? null) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @empty
        <section class="rounded-lg border border-dashed border-slate-300 bg-white px-5 py-8 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-live="polite">
            <h2 class="font-semibold text-slate-950 dark:text-white">No detailed rows to show</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">No records match the selected date range and filters.</p>
        </section>
    @endforelse

    @if($hasEmptyRows)
        <section class="rounded-lg border border-dashed border-slate-300 bg-white px-5 py-8 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-live="polite">
            <h2 class="font-semibold text-slate-950 dark:text-white">No detailed rows to show</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">No records match the selected date range and filters.</p>
        </section>
    @endif
</div>
@endsection

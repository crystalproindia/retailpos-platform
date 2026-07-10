@extends('layouts.admin')

@section('title', 'CRM Pipeline')
@section('page-title', 'CRM Pipeline')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Pipeline</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Pipeline</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Server-rendered columns grouped by configured CRM statuses.</p>
        </section>

        <section class="grid gap-4 xl:grid-cols-4">
            @foreach ($columns as $column)
                <article class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 p-4 dark:border-slate-800">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="font-semibold text-slate-950 dark:text-white">{{ $column['status']->name }}</h2>
                            <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $column['leads']->count() }}</span>
                        </div>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">₹{{ number_format((float) $column['value'], 0) }}</p>
                    </div>
                    <div class="space-y-3 p-4">
                        @forelse ($column['leads'] as $lead)
                            <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                                <a href="{{ route('crm.leads.show', $lead) }}" class="font-medium text-slate-950 dark:text-white">{{ $lead->title }}</a>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $lead->business_name ?? $lead->contact_name ?? 'No account' }}</p>
                                <form method="POST" action="{{ route('crm.pipeline.transition', $lead) }}" class="mt-3 flex gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="status_id" class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-2 py-2 text-xs dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        @foreach ($columns as $target)
                                            <option value="{{ $target['status']->id }}" @selected($target['status']->id === $column['status']->id)>{{ $target['status']->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="rounded-lg bg-slate-950 px-3 py-2 text-xs font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Move</button>
                                </form>
                            </div>
                        @empty
                            <p class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No leads</p>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </section>
    </div>
@endsection

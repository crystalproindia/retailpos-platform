@extends('layouts.admin')

@section('title', 'CRM Settings')
@section('page-title', 'CRM Settings')
@section('breadcrumbs')<span>/</span><span>CRM Settings</span>@endsection

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p class="text-sm font-semibold text-teal-700 dark:text-teal-300">CRM master data</p>
            <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Keep every lead workflow consistent.</h1>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">Lead statuses shape the pipeline. Sources explain where interest came from. Changes apply only to this company and never remove historical lead values.</p>
        </section>

        <div class="grid gap-6 md:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md motion-reduce:transform-none dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 class="text-lg font-semibold text-slate-950 dark:text-white">Lead Statuses</h2><p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Set the stages available when people create, update, and review leads.</p></div>
                    <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-700 dark:bg-teal-950/60 dark:text-teal-200">{{ $statuses->count() }}</span>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    @forelse ($statuses->take(5) as $status)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $status->name }}</span>@empty<span class="text-sm text-slate-500">No statuses configured yet.</span>@endforelse
                </div>
                <a href="{{ route('crm.settings.statuses.index') }}" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300 dark:hover:text-teal-100">Manage lead statuses <x-icon name="chevron-right" class="size-4" /></a>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md motion-reduce:transform-none dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 class="text-lg font-semibold text-slate-950 dark:text-white">Lead Sources</h2><p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Keep inbound, campaign, referral, and manual lead sources clear for reporting.</p></div>
                    <span class="rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-950/60 dark:text-sky-200">{{ $sources->count() }}</span>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    @forelse ($sources->take(5) as $source)<span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $source->name }}</span>@empty<span class="text-sm text-slate-500">No sources configured yet. New leads can still use No source.</span>@endforelse
                </div>
                <a href="{{ route('crm.settings.sources.index') }}" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300 dark:hover:text-teal-100">Manage lead sources <x-icon name="chevron-right" class="size-4" /></a>
            </section>
        </div>
    </div>
@endsection

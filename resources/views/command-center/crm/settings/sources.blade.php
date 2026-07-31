@extends('layouts.admin')

@section('title', 'Lead Sources')
@section('page-title', 'Lead Sources')
@section('breadcrumbs')<span>/</span><a href="{{ route('crm.settings.index') }}" class="hover:text-slate-950 dark:hover:text-white">CRM Settings</a><span>/</span><span>Lead Sources</span>@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><p class="text-sm font-semibold text-teal-700 dark:text-teal-300">CRM settings</p><h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Lead Sources</h1><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Sources are optional. New leads can remain marked as No source when the origin is unknown.</p></div>
            <a href="{{ route('crm.settings.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Back to CRM Settings</a>
        </div>

        <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <section class="h-fit rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Add a source</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use a source once it is meaningful for how your team reports incoming demand.</p>
                <form method="POST" action="{{ route('crm.settings.sources.store') }}" class="mt-5 space-y-4">@csrf
                    <div><label for="name" class="text-sm font-medium">Source name</label><input id="name" name="name" value="{{ old('name') }}" required class="mt-1.5 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><p class="mt-1 text-xs text-slate-500">For example, Referral or Walk-in.</p>@error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label for="description" class="text-sm font-medium">Short description</label><textarea id="description" name="description" rows="3" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">{{ old('description') }}</textarea><p class="mt-1 text-xs text-slate-500">Optional context for your team.</p></div>
                    <div><label for="tone" class="text-sm font-medium">Badge tone</label><select id="tone" name="tone" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach (['neutral','info','success','warning','danger'] as $tone)<option value="{{ $tone }}" @selected(old('tone', 'neutral') === $tone)>{{ str($tone)->headline() }}</option>@endforeach</select></div>
                    <div><label for="color" class="text-sm font-medium">Optional brand colour</label><input id="color" name="color" value="{{ old('color') }}" placeholder="#0f766e" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><p class="mt-1 text-xs text-slate-500">Use a six-digit hex colour.</p></div>
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_default" value="1" class="mt-0.5 rounded border-slate-300 text-teal-600 focus:ring-teal-500" @checked(old('is_default'))><span><span class="font-medium">Make this the default</span><span class="mt-0.5 block text-xs text-slate-500">Optional. No source stays available.</span></span></label>
                    <button class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950">Add source</button>
                </form>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="text-base font-semibold">Configured sources</h2><p class="mt-1 text-sm text-slate-500">Ordering is reflected in the lead form. Removing a source is blocked when leads already use it.</p></div>
                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($sources as $source)
                        <article class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-slate-950 dark:text-white">{{ $source->name }}</h3>@if ($source->is_default)<span class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-700 dark:bg-teal-950/60 dark:text-teal-200">Default</span>@endif @if (! $source->is_active)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Inactive</span>@endif</div><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $source->description ?: 'No description' }} · {{ $source->leads_count }} leads</p></div>
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('crm.settings.sources.move', [$source, 'up']) }}">@csrf<button class="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" aria-label="Move {{ $source->name }} up"><x-icon name="chevron-up" class="size-4" /></button></form>
                                <form method="POST" action="{{ route('crm.settings.sources.move', [$source, 'down']) }}">@csrf<button class="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" aria-label="Move {{ $source->name }} down"><x-icon name="chevron-down" class="size-4" /></button></form>
                                <a href="{{ route('crm.settings.sources.edit', $source) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Edit</a>
                                @if ($source->is_active)<form method="POST" action="{{ route('crm.settings.sources.default', $source) }}">@csrf<button class="rounded-lg px-3 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50 dark:text-teal-300 dark:hover:bg-teal-950/40">Set default</button></form>@endif
                                <form method="POST" action="{{ route('crm.settings.sources.toggle', $source) }}">@csrf<button class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">{{ $source->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                                @if ($source->leads_count === 0)<button type="button" class="rounded-lg p-2 text-rose-600 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/40" data-confirm-trigger data-confirm-action="{{ route('crm.settings.sources.destroy', $source) }}" data-confirm-title="Delete {{ $source->name }}?" data-confirm-message="This unused source will be removed from this company." aria-label="Delete {{ $source->name }}"><x-icon name="trash" class="size-4" /></button>@endif
                            </div>
                        </article>
                    @empty
                        <div class="p-10 text-center"><p class="font-semibold">No lead sources yet</p><p class="mt-2 text-sm text-slate-500">New leads can continue using No source until you add one.</p></div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
    @include('command-center.crm.settings.partials.confirm-dialog')
@endsection

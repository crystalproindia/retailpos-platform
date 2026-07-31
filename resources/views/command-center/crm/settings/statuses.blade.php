@extends('layouts.admin')

@section('title', 'Lead Statuses')
@section('page-title', 'Lead Statuses')
@section('breadcrumbs')<span>/</span><a href="{{ route('crm.settings.index') }}" class="hover:text-slate-950 dark:hover:text-white">CRM Settings</a><span>/</span><span>Lead Statuses</span>@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-6">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div><p class="text-sm font-semibold text-teal-700 dark:text-teal-300">CRM settings</p><h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Lead Statuses</h1><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">The default is selected automatically for new leads. Deactivate a status when it should no longer be offered.</p></div>
            <a href="{{ route('crm.settings.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Back to CRM Settings</a>
        </div>

        <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <section class="h-fit rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Add a status</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use familiar names so the whole team reads the pipeline consistently.</p>
                <form method="POST" action="{{ route('crm.settings.statuses.store') }}" class="mt-5 space-y-4">@csrf
                    <div><label for="name" class="text-sm font-medium">Status name</label><input id="name" name="name" value="{{ old('name') }}" required class="mt-1.5 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><p class="mt-1 text-xs text-slate-500">Shown on every lead.</p>@error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label for="stage_type" class="text-sm font-medium">Pipeline meaning</label><select id="stage_type" name="stage_type" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach ($stageTypes as $stage)<option value="{{ $stage->value }}" @selected(old('stage_type', 'new') === $stage->value)>{{ $stage->label() }}</option>@endforeach</select></div>
                    <div><label for="probability" class="text-sm font-medium">Expected probability</label><input id="probability" type="number" min="0" max="100" name="probability" value="{{ old('probability', 0) }}" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><p class="mt-1 text-xs text-slate-500">Used for pipeline context, not automated decisions.</p></div>
                    <div><label for="tone" class="text-sm font-medium">Badge tone</label><select id="tone" name="tone" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950">@foreach (['neutral','info','success','warning','danger'] as $tone)<option value="{{ $tone }}" @selected(old('tone', 'neutral') === $tone)>{{ str($tone)->headline() }}</option>@endforeach</select></div>
                    <div><label for="color" class="text-sm font-medium">Optional brand colour</label><input id="color" name="color" value="{{ old('color') }}" placeholder="#0f766e" class="mt-1.5 w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"><p class="mt-1 text-xs text-slate-500">Use a six-digit hex colour.</p></div>
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_default" value="1" class="mt-0.5 rounded border-slate-300 text-teal-600 focus:ring-teal-500" @checked(old('is_default'))><span><span class="font-medium">Make this the default</span><span class="mt-0.5 block text-xs text-slate-500">New leads start here.</span></span></label>
                    <button class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950">Add status</button>
                </form>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="text-base font-semibold">Configured statuses</h2><p class="mt-1 text-sm text-slate-500">Use the arrow controls to set the order shown in lead forms.</p></div>
                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($statuses as $status)
                        <article class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold text-slate-950 dark:text-white">{{ $status->name }}</h3>@if ($status->is_default)<span class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-700 dark:bg-teal-950/60 dark:text-teal-200">Default</span>@endif @if (! $status->is_active)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Inactive</span>@endif</div><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $status->stage_type->label() }} · {{ $status->probability }}% · {{ $status->leads_count }} leads</p></div>
                            <div class="flex flex-wrap items-center gap-2">
                                <form method="POST" action="{{ route('crm.settings.statuses.move', [$status, 'up']) }}">@csrf<button class="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" aria-label="Move {{ $status->name }} up"><x-icon name="chevron-up" class="size-4" /></button></form>
                                <form method="POST" action="{{ route('crm.settings.statuses.move', [$status, 'down']) }}">@csrf<button class="rounded-lg border border-slate-200 p-2 text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800" aria-label="Move {{ $status->name }} down"><x-icon name="chevron-down" class="size-4" /></button></form>
                                <a href="{{ route('crm.settings.statuses.edit', $status) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Edit</a>
                                @if ($status->is_active)<form method="POST" action="{{ route('crm.settings.statuses.default', $status) }}">@csrf<button class="rounded-lg px-3 py-2 text-sm font-semibold text-teal-700 hover:bg-teal-50 dark:text-teal-300 dark:hover:bg-teal-950/40">Set default</button></form>@endif
                                <form method="POST" action="{{ route('crm.settings.statuses.toggle', $status) }}">@csrf<button class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">{{ $status->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                                @if ($status->leads_count === 0)<button type="button" class="rounded-lg p-2 text-rose-600 hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/40" data-confirm-trigger data-confirm-action="{{ route('crm.settings.statuses.destroy', $status) }}" data-confirm-title="Delete {{ $status->name }}?" data-confirm-message="This unused status will be removed from this company." aria-label="Delete {{ $status->name }}"><x-icon name="trash" class="size-4" /></button>@endif
                            </div>
                        </article>
                    @empty
                        <div class="p-10 text-center"><p class="font-semibold">No lead statuses yet</p><p class="mt-2 text-sm text-slate-500">Add a first status to make the lead form ready.</p></div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
    @include('command-center.crm.settings.partials.confirm-dialog')
@endsection

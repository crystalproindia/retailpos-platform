@extends('layouts.admin')

@section('title', 'Customize Navigation')
@section('page-title', 'Customize Navigation')
@section('breadcrumbs')<span>/</span><span>Customize Navigation</span>@endsection

@section('content')
    <div class="mx-auto max-w-6xl space-y-6" data-navigation-customizer>
        <section class="rounded-2xl border border-indigo-200 bg-indigo-50 p-6 shadow-sm dark:border-indigo-900/70 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-5 lg:flex-row lg:items-end">
                <div class="max-w-2xl"><p class="text-sm font-semibold text-indigo-700 dark:text-indigo-200">Your Command Center</p><h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Make everyday work easier to reach.</h1><p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Choose the authorized modules you want to see, pin your frequent destinations, and arrange the order that works for you. Access permissions always remain unchanged.</p></div>
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950">Back to dashboard</a>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Recommended view</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A preset starts from your currently authorized module set. It cannot reveal a module you are not permitted to access.</p></div></div>
            <form method="POST" action="{{ route('navigation.preferences.update') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">@csrf @method('PUT')<label class="block min-w-0 flex-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Navigation preset<select name="selected_preset" class="mt-2 w-full"><option value="">Choose a recommended view</option>@foreach($presets as $key => $preset)<option value="{{ $key }}" @selected($preference->selected_preset === $key)>{{ $preset['label'] }}</option>@endforeach</select></label><button name="apply_preset" value="1" class="min-h-11 rounded-xl border border-indigo-300 px-4 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-200 dark:hover:bg-indigo-950/40">Apply preset</button></form>
        </section>

        <form method="POST" action="{{ route('navigation.preferences.update') }}" class="space-y-6" data-navigation-preference-form>
            @csrf @method('PUT')
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Visible modules and shortcuts</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Use the arrows to arrange your preferred order. Pinning creates a Favorites group in the sidebar and a My Shortcuts area on the dashboard.</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ collect($modules)->flatten()->count() }} authorized modules</span></div>

                <div class="mt-5 space-y-6">
                    @foreach($modules as $category => $categoryModules)
                        <section><div class="mb-3 flex items-center justify-between gap-3"><h3 class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500 dark:text-slate-400">{{ $category }}</h3><span class="text-xs text-slate-400">Only modules you can access are listed</span></div>
                            <div class="grid gap-3 md:grid-cols-2" data-navigation-order-list>
                                @foreach($categoryModules as $module)
                                    @php($isVisible = in_array($module->id, $visibleIds, true))
                                    @php($isPinned = in_array($module->id, $pinnedIds, true))
                                    <article class="navigation-preference-card rounded-xl border border-slate-200 bg-slate-50/80 p-4 transition dark:border-slate-800 dark:bg-slate-950/45" data-navigation-item>
                                        <input type="hidden" name="module_order[]" value="{{ $module->id }}">
                                        <div class="flex gap-3"><span class="grid size-10 shrink-0 place-items-center rounded-xl bg-white text-slate-700 shadow-sm dark:bg-slate-800 dark:text-slate-200"><x-icon :name="$module->icon" class="size-5" /></span><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-950 dark:text-white">{{ $module->name }}</p><p class="mt-1 text-sm leading-5 text-slate-500 dark:text-slate-400">{{ $module->description }}</p></div><div class="flex shrink-0 gap-1"><button type="button" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-white hover:text-slate-950 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white" data-navigation-move="up" aria-label="Move {{ $module->name }} up"><x-icon name="chevron-up" class="size-4" /></button><button type="button" class="grid size-9 place-items-center rounded-lg border border-slate-200 text-slate-500 transition hover:bg-white hover:text-slate-950 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white" data-navigation-move="down" aria-label="Move {{ $module->name }} down"><x-icon name="chevron-down" class="size-4" /></button></div></div>
                                            <div class="mt-4 flex flex-wrap gap-4 text-sm"><label class="inline-flex items-center gap-2 font-medium text-slate-700 dark:text-slate-200"><input type="checkbox" name="visible_module_ids[]" value="{{ $module->id }}" @checked($isVisible)> Show in navigation</label><label class="inline-flex items-center gap-2 font-medium text-slate-700 dark:text-slate-200"><input type="checkbox" name="pinned_module_ids[]" value="{{ $module->id }}" @checked($isPinned) @disabled(! $isVisible) data-navigation-pin> Pin shortcut</label></div>
                                        </div></div>
                                    </article>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            </section>

            <div class="sticky-form-actions"><a href="{{ route('dashboard') }}" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</a><button class="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950">Save navigation</button></div>
        </form>

        <section class="rounded-2xl border border-dashed border-slate-300 p-5 dark:border-slate-700"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-semibold text-slate-950 dark:text-white">Hidden modules</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $hiddenModules->isEmpty() ? 'Nothing is hidden right now.' : $hiddenModules->pluck('name')->implode(', ') }}</p></div><button type="button" class="min-h-11 rounded-xl border border-rose-300 px-4 text-sm font-semibold text-rose-700 transition hover:bg-rose-50 dark:border-rose-900 dark:text-rose-200 dark:hover:bg-rose-950/30" data-confirm-trigger data-confirm-action="{{ route('navigation.preferences.reset') }}" data-confirm-method="POST" data-confirm-title="Reset navigation to defaults?" data-confirm-message="Your personal pins, hidden modules, and order will be restored to the recommended defaults." data-confirm-submit-label="Reset navigation">Reset navigation to defaults</button></div></section>

        @include('command-center.crm.settings.partials.confirm-dialog')
    </div>
@endsection

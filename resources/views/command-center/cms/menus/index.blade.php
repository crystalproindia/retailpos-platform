@extends('layouts.admin')

@section('title', 'Navigation Builder')
@section('page-title', 'Navigation Builder')

@section('breadcrumbs')
    <span>/</span>
    <span>CMS</span>
    <span>/</span>
    <span>Menus</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')

        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Review changes to key links such as Products, Modules, Industries, Solutions, Pricing, Book Demo, Talk to Sales, and Case Studies before saving.</div>

        <section class="grid gap-6 xl:grid-cols-[0.75fr_1.25fr]">
            <form method="POST" action="{{ route('cms.menus.store') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                @csrf
                <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Create Menu</h1>
                <div class="mt-5 space-y-4">
                    <input name="name" placeholder="Menu name" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <select name="location" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @foreach ($locations as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input type="hidden" name="is_enabled" value="0">
                        <input type="checkbox" name="is_enabled" value="1" checked class="rounded border-slate-300">
                        Enabled
                    </label>
                    <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Create menu</button>
                </div>
            </form>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Menu Library</h2>
                <form method="GET" action="{{ route('cms.menus.index') }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                    <select name="location" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="">All locations</option>
                        @foreach ($locations as $key => $label)
                            <option value="{{ $key }}" @selected(request('location') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="trashed" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="">Active only</option>
                        <option value="with" @selected(request('trashed') === 'with')>Include trash</option>
                    </select>
                    <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Filter</button>
                </form>
            </section>
        </section>

        <div class="space-y-5">
            @forelse ($menus as $menu)
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-col justify-between gap-4 xl:flex-row">
                        <form method="POST" action="{{ route('cms.menus.update', $menu) }}" class="grid flex-1 gap-3 md:grid-cols-[1fr_180px_140px_auto]">
                            @csrf
                            @method('PUT')
                            <input name="name" value="{{ $menu->name }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <select name="location" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                @foreach ($locations as $key => $label)
                                    <option value="{{ $key }}" @selected($menu->location === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-800">
                                <input type="hidden" name="is_enabled" value="0">
                                <input type="checkbox" name="is_enabled" value="1" @checked($menu->is_enabled) class="rounded border-slate-300">
                                Enabled
                            </label>
                            <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save</button>
                        </form>
                        @if ($menu->trashed())
                            <form method="POST" action="{{ route('cms.menus.restore', $menu->id) }}">
                                @csrf
                                <button class="rounded-lg border border-teal-200 px-4 py-2 text-sm font-semibold text-teal-700 dark:border-teal-800 dark:text-teal-300">Restore</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('cms.menus.destroy', $menu) }}">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 dark:border-rose-800 dark:text-rose-300">Trash</button>
                            </form>
                        @endif
                    </div>

                    <div class="mt-5 grid gap-5 xl:grid-cols-[1fr_0.8fr]">
                        <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800">
                            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                                    <tr>
                                        <th class="px-4 py-3">Label</th>
                                        <th class="px-4 py-3">URL</th>
                                        <th class="px-4 py-3">Order</th>
                                        <th class="px-4 py-3">State</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @forelse ($menu->items as $item)
                                        <tr>
                                            <td class="px-4 py-3 font-medium text-slate-950 dark:text-white">{{ $item->label }}</td>
                                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $item->url }}</td>
                                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $item->sort_order }}</td>
                                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $item->is_enabled ? 'Enabled' : 'Disabled' }}</td>
                                            <td class="px-4 py-3 text-right">
                                                <details class="inline-block text-left">
                                                    <summary class="cursor-pointer text-sm font-semibold text-teal-700 dark:text-teal-300">Edit</summary>
                                                    <form method="POST" action="{{ route('cms.menus.items.update', [$menu, $item]) }}" class="mt-3 grid w-80 gap-2 rounded-lg border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-700 dark:bg-slate-950">
                                                        @csrf
                                                        @method('PUT')
                                                        <input name="label" value="{{ $item->label }}" required class="rounded border border-slate-300 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                                        <input name="url" value="{{ $item->url }}" required class="rounded border border-slate-300 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                                        <select name="parent_id" class="rounded border border-slate-300 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                                            <option value="">No parent</option>
                                                            @foreach ($menu->items->where('id', '!=', $item->id) as $parent)
                                                                <option value="{{ $parent->id }}" @selected($item->parent_id === $parent->id)>{{ $parent->label }}</option>
                                                            @endforeach
                                                        </select>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <input name="icon" value="{{ $item->icon }}" placeholder="Icon" class="rounded border border-slate-300 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                                            <input type="number" name="sort_order" value="{{ $item->sort_order }}" min="0" class="rounded border border-slate-300 bg-white px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-white">
                                                        </div>
                                                        <label class="text-sm text-slate-600 dark:text-slate-300"><input type="hidden" name="opens_new_tab" value="0"><input type="checkbox" name="opens_new_tab" value="1" @checked($item->opens_new_tab) class="rounded border-slate-300"> New tab</label>
                                                        <label class="text-sm text-slate-600 dark:text-slate-300"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked($item->is_enabled) class="rounded border-slate-300"> Enabled</label>
                                                        <button class="rounded bg-slate-950 px-3 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save item</button>
                                                    </form>
                                                </details>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-4 py-6 text-center text-slate-500">No menu items.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <form method="POST" action="{{ route('cms.menus.items.store', $menu) }}" class="space-y-3 rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                            @csrf
                            <p class="font-semibold text-slate-950 dark:text-white">Add Menu Item</p>
                            <input name="label" placeholder="Label" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <input name="url" placeholder="URL or external link" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <select name="parent_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <option value="">No parent</option>
                                @foreach ($menu->items as $parent)
                                    <option value="{{ $parent->id }}">{{ $parent->label }}</option>
                                @endforeach
                            </select>
                            <div class="grid gap-3 md:grid-cols-2">
                                <input name="icon" placeholder="Icon" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                <input type="number" name="sort_order" value="0" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            </div>
                            <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="hidden" name="opens_new_tab" value="0"><input type="checkbox" name="opens_new_tab" value="1" class="rounded border-slate-300"> Open in new tab</label>
                            <input type="hidden" name="is_enabled" value="1">
                            <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Add item</button>
                        </form>
                    </div>
                </section>
            @empty
                <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500 dark:border-slate-700 dark:text-slate-400">No menus found.</div>
            @endforelse
        </div>

        {{ $menus->links() }}
    </div>
@endsection

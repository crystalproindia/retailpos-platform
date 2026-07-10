@extends('layouts.admin')

@section('title', $activeSection['label'].' Settings')
@section('page-title', $activeSection['label'].' Settings')

@section('breadcrumbs')
    <span>/</span>
    <span>Settings</span>
    <span>/</span>
    <span>{{ $activeSection['label'] }}</span>
@endsection

@section('content')
    <div class="grid gap-6 xl:grid-cols-[280px_1fr]">
        <aside class="rounded-lg border border-slate-200 bg-white p-2 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @foreach ($sections as $key => $item)
                <a href="{{ route('settings.show', $key) }}"
                    class="block rounded-md px-3 py-3 text-sm transition {{ $section === $key ? 'bg-slate-950 text-white dark:bg-teal-300 dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                    <span class="font-semibold">{{ $item['label'] }}</span>
                    <span class="mt-1 block text-xs opacity-75">{{ $item['description'] }}</span>
                </a>
            @endforeach
        </aside>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800">
                <h1 class="text-xl font-semibold tracking-normal text-slate-950 dark:text-white">{{ $activeSection['label'] }}</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $activeSection['description'] }}</p>
            </div>

            <form method="POST" action="{{ route('settings.update', $section) }}" class="p-5">
                @csrf
                @method('PUT')

                <div class="grid gap-5 lg:grid-cols-2">
                    @foreach ($activeSection['fields'] as $key => $field)
                        @php
                            $value = old($key, $values[$key] ?? null);
                        @endphp

                        <div class="{{ $field['type'] === 'textarea' ? 'lg:col-span-2' : '' }}">
                            @if ($field['type'] === 'checkbox')
                                <input type="hidden" name="{{ $key }}" value="0">
                                <label class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                                    <span>
                                        <span class="block text-sm font-medium text-slate-800 dark:text-slate-200">{{ $field['label'] }}</span>
                                    </span>
                                    <input type="checkbox" name="{{ $key }}" value="1" @checked((bool) $value)
                                        class="size-5 rounded border-slate-300 text-slate-950 focus:ring-slate-950 dark:border-slate-700">
                                </label>
                            @else
                                <label for="{{ $key }}" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $field['label'] }}</label>

                                @if ($field['type'] === 'textarea')
                                    <textarea id="{{ $key }}" name="{{ $key }}" rows="4"
                                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">{{ $value }}</textarea>
                                @elseif ($field['type'] === 'select')
                                    <select id="{{ $key }}" name="{{ $key }}"
                                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                                        @foreach ($field['options'] as $optionValue => $optionLabel)
                                            <option value="{{ $optionValue }}" @selected($value === $optionValue)>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <input id="{{ $key }}" name="{{ $key }}" type="{{ $field['type'] }}" value="{{ $value }}"
                                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-slate-950 focus:ring-4 focus:ring-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:focus:ring-slate-800">
                                @endif
                            @endif

                            @error($key)
                                <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <div class="mt-6 flex justify-end border-t border-slate-200 pt-5 dark:border-slate-800">
                    <button type="submit" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200 dark:focus:ring-teal-800">
                        Save settings
                    </button>
                </div>
            </form>
        </section>
    </div>
@endsection

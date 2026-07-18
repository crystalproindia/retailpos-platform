@extends('layouts.admin')

@section('title', 'CMS Settings')
@section('page-title', 'CMS Settings')

@section('breadcrumbs')
    <span>/</span>
    <span>CMS</span>
    <span>/</span>
    <span>Settings</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')

        @php
            $settingsRoute = request()->routeIs('website.*') ? 'website.settings.update' : 'cms.settings.update';
        @endphp

        <section class="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
            <form method="POST" action="{{ route($settingsRoute) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                @csrf
                @method('PUT')
                <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Global Website Settings</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Default website identity and contact content consumed by the public site.</p>

                <div class="mt-5 space-y-6">
                    @foreach (collect($definitions)->groupBy('group') as $group => $groupDefinitions)
                        <section id="{{ str($group)->kebab() }}">
                            <h2 class="text-sm font-semibold text-slate-950 dark:text-white">{{ str($group)->headline() }}</h2>
                            <div class="mt-3 grid gap-4 md:grid-cols-2">
                    @foreach ($groupDefinitions as $key => $definition)
                        @php
                            $setting = $settings->get($key);
                            $value = old($key, $setting?->value ?? $setting?->media_id ?? ($definition['default'] ?? null));
                            $wide = in_array($definition['type'], ['textarea'], true);
                        @endphp
                        <div class="{{ $wide ? 'md:col-span-2' : '' }}">
                            <label for="{{ $key }}" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $definition['label'] }}</label>
                            @if (! empty($definition['help']))
                                <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $definition['help'] }}</p>
                            @endif
                            @if ($definition['type'] === 'textarea')
                                <textarea id="{{ $key }}" name="{{ $key }}" rows="4" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ $value }}</textarea>
                            @elseif ($definition['type'] === 'media')
                                <input id="{{ $key }}" type="number" name="{{ $key }}" value="{{ $value }}" placeholder="Media ID" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @elseif ($definition['type'] === 'boolean')
                                <input type="hidden" name="{{ $key }}" value="0">
                                <label class="mt-3 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input id="{{ $key }}" type="checkbox" name="{{ $key }}" value="1" @checked(old($key, (bool) $value)) class="rounded border-slate-300"> Enabled</label>
                            @else
                                <input id="{{ $key }}" name="{{ $key }}" value="{{ $value }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @endif
                            <label class="mt-2 flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="clear_settings[]" value="{{ $key }}" class="rounded border-slate-300"> Clear this saved value</label>
                            @error($key)
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>

                <div class="mt-6 flex justify-end">
                    <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save settings</button>
                </div>
            </form>

            <form method="POST" action="{{ route('cms.settings.footer.update') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                @csrf
                @method('PUT')
                <h2 class="text-xl font-semibold text-slate-950 dark:text-white">Footer Manager</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Company information, legal links foundation, and footer contact data.</p>

                <div class="mt-5 space-y-4">
                    <input name="company_name" value="{{ old('company_name', $footer->company_name) }}" placeholder="Company name" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <textarea name="address" rows="3" placeholder="Address" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('address', $footer->address) }}</textarea>
                    <div class="grid gap-4 md:grid-cols-2">
                        <input name="phone" value="{{ old('phone', $footer->phone) }}" placeholder="Phone" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <input name="email" value="{{ old('email', $footer->email) }}" placeholder="Email" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    </div>
                    <input name="whatsapp" value="{{ old('whatsapp', $footer->whatsapp) }}" placeholder="WhatsApp" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <textarea name="business_hours" rows="3" placeholder="Business hours" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('business_hours', $footer->business_hours) }}</textarea>
                    <input name="google_map_url" value="{{ old('google_map_url', $footer->google_map_url) }}" placeholder="Google Map URL" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <input name="copyright_text" value="{{ old('copyright_text', $footer->copyright_text) }}" placeholder="Copyright text" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </div>

                <div class="mt-6 flex justify-end">
                    <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save footer</button>
                </div>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Social Links</h2>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                @forelse ($socialLinks as $link)
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <p class="font-medium text-slate-950 dark:text-white">{{ $link->platform }}</p>
                        <p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">{{ $link->url }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">Social link management foundation is ready; links can be added by services in future workflows.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection

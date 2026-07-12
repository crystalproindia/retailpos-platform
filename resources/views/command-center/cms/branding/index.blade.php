@extends('layouts.admin')
@section('title', 'Branding Manager') @section('page-title', 'Branding Manager')
@section('breadcrumbs')<span>/</span><a href="{{ route('cms.dashboard') }}">CMS</a><span>/</span><span>Branding</span>@endsection
@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')
        <form method="POST" action="{{ route('cms.branding.update') }}" class="space-y-6">
            @csrf @method('PUT')
            <section class="cms-panel flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="cms-kicker">Brand system</p><h1 class="mt-2 text-xl font-semibold text-slate-900">Make the website recognisably yours</h1><p class="mt-2 text-sm leading-6 text-slate-600">Review logos, visual identity, and default conversion language in one easy-to-scan workspace.</p></div><a href="{{ route('cms.media.index') }}" class="cms-button-secondary">Open media library</a></section>
            <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
                <x-form-section title="Brand details" help="Use approved assets and customer-facing language. Media files remain managed in the Media Library.">
                    <div class="grid gap-5 md:grid-cols-2">
                        @foreach ($definitions as $key => $definition)
                            <label class="cms-control-label {{ in_array($key, ['brand_tagline', 'default_cta_link']) ? 'md:col-span-2' : '' }}">
                                {{ $definition['label'] }}
                                @if ($definition['type'] === 'media')
                                    <select name="{{ $key }}" class="mt-2 block w-full"><option value="">No media selected</option>@foreach ($media as $item)<option value="{{ $item->id }}" @selected(old($key, $settings[$key]?->media_id) == $item->id)>{{ $item->filename }}</option>@endforeach</select>
                                    <span class="cms-control-help">Select an existing asset from the library.</span>
                                @else
                                    <input name="{{ $key }}" value="{{ old($key, $settings[$key]?->value) }}" @if (str_contains($key, 'color')) placeholder="#0f766e" @endif class="mt-2 block w-full">
                                @endif
                            </label>
                        @endforeach
                    </div>
                </x-form-section>
                <aside class="space-y-5">
                    <section class="cms-panel"><p class="cms-kicker">Asset preview</p><h2 class="mt-2 text-lg font-semibold text-slate-900">Logo placements</h2><div class="mt-5 grid grid-cols-2 gap-3"><div class="rounded-xl border border-slate-200 bg-white p-4"><p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Light logo</p><div class="mt-3 grid h-20 place-items-center rounded-lg bg-slate-100 font-semibold text-slate-400">{{ $settings['light_logo']?->media_id ? 'Selected asset' : 'Logo preview' }}</div></div><div class="rounded-xl bg-slate-900 p-4"><p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Dark logo</p><div class="mt-3 grid h-20 place-items-center rounded-lg bg-slate-800 font-semibold text-slate-300">{{ $settings['dark_logo']?->media_id ? 'Selected asset' : 'Logo preview' }}</div></div></div><div class="mt-3 rounded-xl border border-slate-200 p-4"><p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Favicon</p><div class="mt-3 grid size-12 place-items-center rounded-lg bg-teal-600 text-sm font-semibold text-white">{{ $settings['favicon']?->media_id ? 'F' : 'RP' }}</div></div></section>
                    <section class="cms-panel"><p class="cms-kicker">Brand colours</p><div class="mt-4 flex flex-wrap gap-3">@foreach (['primary_brand_color' => 'Primary', 'secondary_brand_color' => 'Secondary', 'accent_brand_color' => 'Accent'] as $key => $label)<div class="rounded-xl border border-slate-200 p-3"><span class="block size-9 rounded-lg border border-slate-200" style="background: {{ $settings[$key]?->value ?: '#cbd5e1' }}"></span><p class="mt-2 text-xs font-semibold text-slate-700">{{ $label }}</p></div>@endforeach</div><div class="mt-5 rounded-xl bg-slate-900 p-4 text-white"><p class="font-semibold">{{ $settings['brand_name']?->value ?: 'RetailPOS' }}</p><p class="mt-1 text-sm text-slate-300">{{ $settings['brand_tagline']?->value ?: 'Your brand tagline preview' }}</p><span class="mt-4 inline-flex rounded-lg px-3 py-2 text-sm font-semibold text-white" style="background: {{ $settings['primary_brand_color']?->value ?: '#0f766e' }}">{{ $settings['default_cta_text']?->value ?: 'Call to action' }}</span></div></section>
                </aside>
            </div>
            <x-sticky-form-actions><a href="{{ route('cms.dashboard') }}" class="cms-button-secondary">Cancel</a><button class="cms-button-primary">Save branding</button></x-sticky-form-actions>
        </form>
    </div>
@endsection

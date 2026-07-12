@extends('layouts.admin')

@section('title', 'Homepage Builder')
@section('page-title', 'Homepage Builder')
@section('breadcrumbs')<span>/</span><span>CMS</span><span>/</span><span>Homepage</span>@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')
        <section class="cms-panel flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="cms-kicker">Website section builder</p><h1 class="mt-2 text-xl font-semibold text-slate-900">Homepage Builder</h1><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Edit individual homepage sections with a simple editorial preview. These are admin-only previews, not a public website renderer.</p></div>
            <span class="cms-chip">{{ $sections->count() }} sections</span>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            @foreach ($sections as $section)
                @php($previewType = match ($section->key) { 'hero' => 'Hero', 'features' => 'Features', 'client_logos' => 'Logo row', 'case_studies' => 'Case studies', 'testimonials' => 'Testimonials', 'faq' => 'FAQ', default => 'Content section' })
                <form method="POST" action="{{ route('cms.homepage.update', $section->key) }}" class="cms-section-card">
                    @csrf
                    @method('PUT')
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div><div class="flex items-center gap-2"><h2 class="text-base font-semibold text-slate-900">{{ $section->name }}</h2><span class="cms-chip">{{ $previewType }}</span></div><p class="mt-1 text-sm text-slate-500">{{ $section->key }} · position {{ $section->sort_order }}</p></div>
                        <label class="cms-toggle"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $section->is_enabled))> Enabled</label>
                    </div>

                    <div class="cms-preview mt-5">
                        <div class="cms-preview-header"><span>Admin preview</span><span>{{ $section->media_id ? 'Media selected' : 'Media optional' }}</span></div>
                        <div class="p-4">
                            @if ($section->key === 'hero')
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-teal-700">{{ $section->eyebrow ?: 'Homepage hero' }}</p><p class="mt-2 text-xl font-semibold text-slate-900">{{ $section->heading ?: 'Add a clear hero heading' }}</p><p class="mt-2 text-sm leading-6 text-slate-600">{{ $section->subheading ?: 'A concise supporting message appears here.' }}</p><span class="mt-4 inline-flex rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white">{{ $section->cta_label ?: 'Call to action' }}</span>
                            @elseif ($section->key === 'features')
                                <div class="grid grid-cols-3 gap-2">@for($i = 1; $i <= 3; $i++)<div class="rounded-lg border border-slate-200 bg-white p-3"><span class="block size-6 rounded bg-teal-100"></span><span class="mt-3 block h-2 rounded bg-slate-200"></span><span class="mt-2 block h-2 w-2/3 rounded bg-slate-100"></span></div>@endfor</div>
                            @elseif ($section->key === 'client_logos')
                                <div class="grid grid-cols-4 gap-2">@for($i = 1; $i <= 4; $i++)<div class="grid h-12 place-items-center rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-400">Logo</div>@endfor</div>
                            @elseif ($section->key === 'case_studies')
                                <div class="grid grid-cols-2 gap-3"><div class="rounded-lg bg-teal-700 p-4 text-white"><span class="text-xs text-teal-100">Case study</span><span class="mt-5 block h-2 rounded bg-white/60"></span></div><div class="rounded-lg border border-slate-200 bg-white p-4"><span class="text-xs text-slate-400">Result metric</span><span class="mt-5 block h-2 rounded bg-slate-200"></span></div></div>
                            @elseif ($section->key === 'testimonials')
                                <div class="rounded-lg border border-slate-200 bg-white p-4"><span class="text-lg text-teal-600">“</span><span class="ml-1 text-sm italic text-slate-600">Approved customer quote preview</span><span class="mt-3 block h-2 w-1/3 rounded bg-slate-200"></span></div>
                            @elseif ($section->key === 'faq')
                                <div class="space-y-2">@for($i = 1; $i <= 3; $i++)<div class="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600"><span>Frequently asked question</span><span>+</span></div>@endfor</div>
                            @else
                                <p class="text-sm font-medium text-slate-800">{{ $section->heading ?: 'Section heading preview' }}</p><p class="mt-2 text-sm leading-6 text-slate-600">{{ $section->content ?: 'Add concise section content to see it represented in this builder preview.' }}</p>
                            @endif
                        </div>
                    </div>

                    <details class="mt-5 group" open>
                        <summary class="cursor-pointer list-none text-sm font-semibold text-teal-700"><span class="group-open:hidden">Edit section</span><span class="hidden group-open:inline">Hide editor</span></summary>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <label class="cms-control-label md:col-span-2">Eyebrow<input name="eyebrow" value="{{ old('eyebrow', $section->eyebrow) }}" placeholder="Short label above heading" class="mt-2 block w-full"></label>
                            <label class="cms-control-label md:col-span-2">Heading<input name="heading" value="{{ old('heading', $section->heading) }}" placeholder="Section heading" class="mt-2 block w-full"></label>
                            <label class="cms-control-label md:col-span-2">Supporting copy<textarea name="content" rows="5" placeholder="Add helpful, readable content">{{ old('content', $section->content) }}</textarea></label>
                            <label class="cms-control-label">CTA label<input name="cta_label" value="{{ old('cta_label', $section->cta_label) }}" placeholder="Book a demo" class="mt-2 block w-full"></label>
                            <label class="cms-control-label">CTA URL<input name="cta_url" value="{{ old('cta_url', $section->cta_url) }}" placeholder="/contact" class="mt-2 block w-full"></label>
                            <label class="cms-control-label">Background style<input name="background_style" value="{{ old('background_style', $section->background_style) }}" placeholder="Soft neutral" class="mt-2 block w-full"></label>
                            <label class="cms-control-label">Layout style<input name="layout_style" value="{{ old('layout_style', $section->layout_style) }}" placeholder="Split content" class="mt-2 block w-full"></label>
                            <label class="cms-control-label">Media ID<input type="number" name="media_id" value="{{ old('media_id', $section->media_id) }}" placeholder="Optional media ID" class="mt-2 block w-full"></label>
                            <label class="cms-control-label">Sort order<input type="number" name="sort_order" value="{{ old('sort_order', $section->sort_order) }}" class="mt-2 block w-full"></label>
                        </div>
                        <div class="mt-5 flex justify-end"><button class="cms-button-primary">Save section</button></div>
                    </details>
                </form>
            @endforeach
        </div>
    </div>
@endsection

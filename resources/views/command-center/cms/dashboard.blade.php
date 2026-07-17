@extends('layouts.admin')

@section('title', 'Website Control Center')
@section('page-title', 'Website Control Center')
@section('breadcrumbs')<span>/</span><span>CMS</span>@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')

        <section class="cms-panel overflow-hidden p-0">
            <div class="bg-gradient-to-br from-teal-700 via-teal-600 to-cyan-700 px-6 py-7 text-white sm:px-8">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-teal-100">CMS Pro</p>
                        <h1 class="mt-3 text-2xl font-semibold sm:text-3xl">Website Control Center</h1>
                        <p class="mt-3 text-sm leading-6 text-teal-50">A calm workspace for the pages, brand system, content library, and website metadata your team manages every day.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('cms.pages.create') }}" class="inline-flex min-h-11 items-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-teal-800 shadow-sm transition hover:bg-teal-50">New page</a>
                        <a href="{{ route('cms.case-studies.create') }}" class="inline-flex min-h-11 items-center rounded-lg border border-white/30 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-white/10">New case study</a>
                    </div>
                </div>
            </div>
            <div class="grid gap-px bg-slate-200 sm:grid-cols-2 xl:grid-cols-4">
                @foreach (['Total pages' => $dashboard['counts']['pages'], 'Published' => $dashboard['counts']['published_pages'], 'Drafts' => $dashboard['counts']['draft_pages'], 'Scheduled' => $dashboard['counts']['scheduled_pages'], 'Articles' => $dashboard['counts']['articles'], 'Client logos' => $dashboard['counts']['client_logos'], 'Case studies' => $dashboard['counts']['case_studies'], 'Testimonials' => $dashboard['counts']['testimonials'], 'Media files' => $dashboard['counts']['media']] as $label => $value)
                    <article class="bg-white px-5 py-4">
                        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
                        <p class="mt-1 text-3xl font-semibold text-slate-900">{{ $value }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">
            <section class="cms-panel">
                <div class="flex flex-wrap items-start justify-between gap-4"><div><p class="cms-kicker">Website Content</p><h2 class="mt-2 text-lg font-semibold text-slate-900">Content editor</h2><p class="mt-1 text-sm leading-6 text-slate-600">Manage simple, reusable website pages and sections without editing code.</p></div><a href="{{ route('cms.content.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">Open editor</a></div>
                <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">@foreach(['Pages' => $contentEditor['pages'], 'Published' => $contentEditor['published'], 'Drafts' => $contentEditor['drafts'], 'Hidden sections' => $contentEditor['disabled_sections']] as $label => $value)<div class="cms-subtle-panel"><p class="text-xs font-medium text-slate-500">{{ $label }}</p><p class="mt-1 text-2xl font-semibold text-slate-900">{{ $value }}</p></div>@endforeach</div>
            </section>
            <section class="cms-panel">
                <div class="flex items-start justify-between gap-4">
                    <div><p class="cms-kicker">Publishing readiness</p><h2 class="mt-2 text-lg font-semibold text-slate-900">Your website essentials</h2></div>
                    <a href="{{ route('cms.homepage.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">Open builder</a>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    @foreach ($dashboard['readiness'] as $item)
                        <div class="cms-subtle-panel flex items-center justify-between gap-3">
                            <span class="font-semibold text-slate-800">{{ $item['label'] }}</span>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $item['ready'] ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">{{ $item['ready'] ? 'Ready' : 'Review' }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <a href="{{ route('cms.branding.index') }}" class="cms-subtle-panel text-sm font-semibold text-slate-700 transition hover:border-teal-200 hover:bg-teal-50 hover:text-teal-800">Brand system</a>
                    <a href="{{ route('cms.homepage.index') }}" class="cms-subtle-panel text-sm font-semibold text-slate-700 transition hover:border-teal-200 hover:bg-teal-50 hover:text-teal-800">Homepage</a>
                    <a href="{{ route('cms.seo.index') }}" class="cms-subtle-panel text-sm font-semibold text-slate-700 transition hover:border-teal-200 hover:bg-teal-50 hover:text-teal-800">SEO center</a>
                </div>
            </section>

            <section class="cms-panel">
                <p class="cms-kicker">SEO health</p><h2 class="mt-2 text-lg font-semibold text-slate-900">Content review queue</h2>
                <dl class="mt-5 space-y-3">
                    @foreach (['Missing meta titles' => $dashboard['warnings']['missing_titles'], 'Missing descriptions' => $dashboard['warnings']['missing_descriptions'], 'Missing OG images' => $dashboard['warnings']['missing_og_images'], 'Redirects' => $dashboard['counts']['redirects']] as $label => $value)
                        <div class="flex items-center justify-between rounded-lg border border-slate-100 px-4 py-3">
                            <dt class="text-sm text-slate-600">{{ $label }}</dt><dd class="text-lg font-semibold text-slate-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
            <section class="cms-panel">
                <p class="cms-kicker">Content library</p><h2 class="mt-2 text-lg font-semibold text-slate-900">Reusable proof points</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">Keep client logos, case studies, testimonials, trust metrics, and calls to action ready for future website surfaces.</p>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <a href="{{ route('cms.client-logos.index') }}" class="cms-subtle-panel text-sm font-semibold text-slate-700">Client logos</a>
                    <a href="{{ route('cms.testimonials.index') }}" class="cms-subtle-panel text-sm font-semibold text-slate-700">Testimonials</a>
                    <a href="{{ route('cms.trust-metrics.index') }}" class="cms-subtle-panel text-sm font-semibold text-slate-700">Trust metrics</a>
                    <a href="{{ route('cms.ctas.index') }}" class="cms-subtle-panel text-sm font-semibold text-slate-700">CTA blocks</a>
                </div>
            </section>
            <section class="cms-panel">
                <div class="flex items-start justify-between gap-4"><div><p class="cms-kicker">Recent pages</p><h2 class="mt-2 text-lg font-semibold text-slate-900">Latest content changes</h2></div><a href="{{ route('cms.pages.index') }}" class="text-sm font-semibold text-teal-700 hover:text-teal-800">View pages</a></div>
                <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($dashboard['recentPages'] as $page)
                        <a href="{{ route('cms.pages.edit', $page) }}" class="cms-section-card">
                            <p class="truncate font-semibold text-slate-900">{{ $page->title }}</p>
                            <p class="mt-1 truncate text-xs text-slate-500">/{{ $page->slug }}</p>
                            <div class="mt-4 flex items-center justify-between gap-2"><x-status-badge :status="$page->status"/><span class="text-xs text-slate-500">{{ $page->updated_at->diffForHumans() }}</span></div>
                        </a>
                    @empty
                        <div class="cms-subtle-panel col-span-full text-center text-sm text-slate-500">No website pages have been created yet.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
@endsection

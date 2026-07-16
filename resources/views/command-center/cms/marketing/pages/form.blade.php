@extends('layouts.admin')

@section('title', $page ? 'Edit page' : 'Create page')
@section('page-title', $page ? 'Edit page' : 'Create page')

@section('content')
    @php($base = $kind === 'seo' ? 'cms.seo-pages' : 'cms.landing-pages')
    @php($seo = $page?->seo)
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')
        <form method="POST" action="{{ $page ? route($base.'.update', $page) : route($base.'.store') }}" class="grid gap-6 xl:grid-cols-[1.25fr_0.75fr]">@csrf @if($page) @method('PUT') @endif
            <section class="space-y-5 rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div><label class="text-sm font-medium text-slate-700 dark:text-slate-200">Route path</label><input name="route_path" required value="{{ old('route_path', $page?->route_path) }}" placeholder="/products/retail-erp" class="mt-2 w-full"><p class="mt-1 text-xs text-slate-500">The public website route this content describes. Page key: {{ $page?->slug ?? 'derived from the route when saved' }}.</p>@error('route_path')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                <div class="grid gap-4 md:grid-cols-2"><div><label class="text-sm font-medium text-slate-700 dark:text-slate-200">Title</label><input name="title" required value="{{ old('title', $page?->title) }}" class="mt-2 w-full"></div><div><label class="text-sm font-medium text-slate-700 dark:text-slate-200">H1</label><input name="h1" value="{{ old('h1', $page?->h1) }}" class="mt-2 w-full"></div></div>
                <div><label class="text-sm font-medium text-slate-700 dark:text-slate-200">Intro content</label><textarea name="intro_content" rows="4" class="mt-2 w-full">{{ old('intro_content', $page?->intro_content) }}</textarea></div>
                <div><label class="text-sm font-medium text-slate-700 dark:text-slate-200">Footer SEO content</label><textarea name="footer_seo_content" rows="5" class="mt-2 w-full">{{ old('footer_seo_content', $page?->footer_seo_content) }}</textarea></div>
                @if($kind === 'landing')
                    <div class="grid gap-4 md:grid-cols-2"><input name="primary_cta_label" value="{{ old('primary_cta_label', $page?->primary_cta_label) }}" placeholder="Primary CTA label"><input name="primary_cta_url" value="{{ old('primary_cta_url', $page?->primary_cta_url) }}" placeholder="Primary CTA URL"><input name="secondary_cta_label" value="{{ old('secondary_cta_label', $page?->secondary_cta_label) }}" placeholder="Secondary CTA label"><input name="secondary_cta_url" value="{{ old('secondary_cta_url', $page?->secondary_cta_url) }}" placeholder="Secondary CTA URL"></div>
                    <div class="grid gap-4"><textarea name="content_sections" rows="5" placeholder='Content sections JSON, for example [{"type":"features","title":"..."}]' class="w-full">{{ old('content_sections', $page?->content_sections ? json_encode($page->content_sections, JSON_PRETTY_PRINT) : '') }}</textarea><textarea name="faq_items" rows="4" placeholder='FAQ JSON, for example [{"question":"...","answer":"..."}]' class="w-full">{{ old('faq_items', $page?->faq_items ? json_encode($page->faq_items, JSON_PRETTY_PRINT) : '') }}</textarea></div>
                @endif
            </section>
            <aside class="space-y-5">
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="font-semibold text-slate-950 dark:text-white">Search preview</h2><p class="mt-4 text-lg text-blue-700">{{ old('meta_title', $seo?->meta_title ?: $page?->title ?: 'Page title') }}</p><p class="text-sm text-emerald-700">{{ old('route_path', $page?->route_path ?: '/') }}</p><p class="mt-1 text-sm text-slate-600">{{ old('meta_description', $seo?->meta_description ?: 'Add a clear, useful search description.') }}</p></section>
                <section class="space-y-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="font-semibold text-slate-950 dark:text-white">SEO</h2><input name="meta_title" maxlength="255" value="{{ old('meta_title', $seo?->meta_title) }}" placeholder="Meta title (50–60 characters)" class="w-full"><p class="-mt-2 text-xs text-slate-500">{{ str(old('meta_title', $seo?->meta_title))->length() }} characters. Recommended: 50-60.</p><textarea name="meta_description" rows="3" placeholder="Meta description (140–160 characters)" class="w-full">{{ old('meta_description', $seo?->meta_description) }}</textarea><p class="-mt-2 text-xs text-slate-500">{{ str(old('meta_description', $seo?->meta_description))->length() }} characters. Recommended: 140-160.</p><input name="meta_keywords" value="{{ old('meta_keywords', $seo?->meta_keywords) }}" placeholder="Meta keywords" class="w-full"><input name="canonical_url" value="{{ old('canonical_url', $seo?->canonical_url) }}" placeholder="Canonical URL" class="w-full"><input type="number" name="og_image_id" value="{{ old('og_image_id', $seo?->og_image_id) }}" placeholder="Open Graph media ID" class="w-full"><input type="number" name="twitter_image_id" value="{{ old('twitter_image_id', $seo?->twitter_image_id) }}" placeholder="Twitter media ID" class="w-full"><textarea name="schema_json" rows="5" placeholder="Schema JSON" class="w-full">{{ old('schema_json', $page?->schema_json) }}</textarea><p class="text-xs text-slate-500">Invalid schema JSON is rejected before save.</p></section>
                <section class="space-y-3 rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"><select name="page_type" class="w-full">@foreach($kind === 'seo' ? ['seo'] : ['landing','product','industry','module','solution','location','comparison'] as $type)<option value="{{ $type }}" @selected(old('page_type', $page?->page_type ?: $type) === $type)>{{ str($type)->headline() }}</option>@endforeach</select><select name="status" class="w-full">@foreach(['draft','published','scheduled','archived'] as $status)<option value="{{ $status }}" @selected(old('status', $page?->status ?: 'draft') === $status)>{{ str($status)->headline() }}</option>@endforeach</select><label class="text-xs font-medium text-slate-600 dark:text-slate-300">Publish at<input type="datetime-local" name="scheduled_for" value="{{ old('scheduled_for', $page?->scheduled_for?->format('Y-m-d\\TH:i')) }}" class="mt-1 w-full"></label><label class="flex gap-2 text-sm"><input type="hidden" name="robots_index" value="0"><input type="checkbox" name="robots_index" value="1" @checked(old('robots_index', $page?->robots_index ?? true))> Allow indexing</label><label class="flex gap-2 text-sm"><input type="hidden" name="robots_follow" value="0"><input type="checkbox" name="robots_follow" value="1" @checked(old('robots_follow', $page?->robots_follow ?? true))> Allow following links</label><label class="flex gap-2 text-sm"><input type="hidden" name="include_in_sitemap" value="0"><input type="checkbox" name="include_in_sitemap" value="1" @checked(old('include_in_sitemap', $page?->include_in_sitemap ?? true))> Include in sitemap</label><input type="number" min="0" max="1" step="0.1" name="sitemap_priority" value="{{ old('sitemap_priority', $page?->sitemap_priority ?? '0.5') }}" class="w-full"><select name="sitemap_changefreq" class="w-full">@foreach(['weekly','daily','monthly','yearly'] as $frequency)<option value="{{ $frequency }}" @selected(old('sitemap_changefreq', $page?->sitemap_changefreq ?? 'weekly') === $frequency)>{{ str($frequency)->headline() }}</option>@endforeach</select><button class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save page</button></section>
            </aside>
        </form>
        @if ($page)
            <section class="flex flex-wrap gap-3">
                @if ($page->status !== 'published')
                    <form method="POST" action="{{ route($base.'.publish', $page) }}">@csrf<button class="rounded-lg border border-teal-300 px-3 py-2 text-sm font-semibold text-teal-700">Publish</button></form>
                @endif
                @if ($page->status === 'published')
                    <form method="POST" action="{{ route($base.'.unpublish', $page) }}">@csrf<button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700">Unpublish</button></form>
                @endif
                <form method="POST" action="{{ route($base.'.archive', $page) }}">@csrf<button class="rounded-lg border border-rose-300 px-3 py-2 text-sm font-semibold text-rose-700">Archive</button></form>
            </section>
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-semibold text-slate-950 dark:text-white">Revision history</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($page->revisions->take(5) as $revision)
                        <p class="text-sm text-slate-600 dark:text-slate-300">Revision {{ $revision->revision_number }}: {{ str($revision->status)->headline() }} by {{ $revision->user?->name ?? 'System' }}</p>
                    @empty
                        <p class="text-sm text-slate-500">The first saved change will appear here.</p>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
@endsection

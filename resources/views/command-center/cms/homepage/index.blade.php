@extends('layouts.admin')

@section('title', 'Homepage Builder')
@section('page-title', 'Homepage Builder')

@section('breadcrumbs')
    <span>/</span>
    <span>CMS</span>
    <span>/</span>
    <span>Homepage</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Homepage Builder</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">Manage every homepage section independently. The public site can consume these records without hardcoded homepage text.</p>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            @foreach ($sections as $section)
                <form method="POST" action="{{ route('cms.homepage.update', $section->key) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    @csrf
                    @method('PUT')
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-950 dark:text-white">{{ $section->name }}</h2>
                            <p class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-400">{{ $section->key }}</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <input type="hidden" name="is_enabled" value="0">
                            <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $section->is_enabled)) class="rounded border-slate-300">
                            Enabled
                        </label>
                    </div>

                    <div class="mt-5 space-y-4">
                        <input name="heading" value="{{ old('heading', $section->heading) }}" placeholder="Heading" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <input name="subheading" value="{{ old('subheading', $section->subheading) }}" placeholder="Subheading" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <textarea name="content" rows="4" placeholder="Content" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('content', $section->content) }}</textarea>
                        <div class="grid gap-4 md:grid-cols-2">
                            <input name="cta_label" value="{{ old('cta_label', $section->cta_label) }}" placeholder="CTA label" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <input name="cta_url" value="{{ old('cta_url', $section->cta_url) }}" placeholder="CTA URL" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <input type="number" name="media_id" value="{{ old('media_id', $section->media_id) }}" placeholder="Media ID" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <input type="number" name="sort_order" value="{{ old('sort_order', $section->sort_order) }}" placeholder="Sort order" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        </div>
                    </div>

                    <div class="mt-5 flex justify-end">
                        <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save section</button>
                    </div>
                </form>
            @endforeach
        </div>
    </div>
@endsection

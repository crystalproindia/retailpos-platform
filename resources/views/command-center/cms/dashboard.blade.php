@extends('layouts.admin')

@section('title', 'CMS')
@section('page-title', 'CMS')

@section('breadcrumbs')
    <span>/</span>
    <span>CMS</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <p class="text-sm font-medium text-teal-700 dark:text-teal-300">Enterprise CMS</p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-normal text-slate-950 dark:text-white">Website Control Center</h1>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">Manage RetailPOS.biz content, SEO, media, menus, homepage sections, and global website settings without touching application code.</p>
                </div>
                <a href="{{ route('cms.pages.create') }}" class="inline-flex items-center justify-center rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">
                    New page
                </a>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pages</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-white">{{ $pageCount }}</p>
            </article>
            <article class="rounded-lg border border-teal-200 bg-teal-50 p-5 shadow-sm dark:border-teal-800 dark:bg-teal-950">
                <p class="text-sm font-medium text-teal-700 dark:text-teal-200">Published</p>
                <p class="mt-3 text-3xl font-semibold text-teal-950 dark:text-teal-50">{{ $publishedPageCount }}</p>
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Media Files</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-white">{{ $mediaCount }}</p>
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Menus</p>
                <p class="mt-3 text-3xl font-semibold text-slate-950 dark:text-white">{{ $menuCount }}</p>
            </article>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Homepage Builder</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Every primary homepage section is managed as content.</p>
                </div>
                <a href="{{ route('cms.homepage.index') }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Manage</a>
            </div>
            <div class="mt-5 grid gap-3 md:grid-cols-3">
                @foreach ($homepageSections->take(6) as $section)
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <p class="font-medium text-slate-950 dark:text-white">{{ $section->name }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $section->is_enabled ? 'Enabled' : 'Disabled' }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection

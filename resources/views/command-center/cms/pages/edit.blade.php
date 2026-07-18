@extends('layouts.admin')

@section('title', 'Edit CMS Page')
@section('page-title', 'Edit CMS Page')

@section('breadcrumbs')
    <span>/</span>
    <span>CMS</span>
    <span>/</span>
    <span>{{ $page->title }}</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div>
                <p class="text-sm text-slate-500 dark:text-slate-400">Current status</p>
                <p class="mt-1 font-semibold text-slate-950 dark:text-white">{{ str($page->status)->headline() }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route($routePrefix.'.pages.preview', $page) }}">@csrf<button class="rounded-lg border border-sky-300 px-4 py-2 text-sm font-semibold text-sky-700">Preview Draft</button></form>
                <a href="{{ route($routePrefix.'.pages.revisions.index', $page) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">Revision History</a>
                <form method="POST" action="{{ route($routePrefix.'.pages.publish', $page) }}">
                    @csrf
                    <button class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-700">Publish</button>
                </form>
                <form method="POST" action="{{ route($routePrefix.'.pages.unpublish', $page) }}">
                    @csrf
                    <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Unpublish</button>
                </form>
                <form method="POST" action="{{ route($routePrefix.'.pages.destroy', $page) }}">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950">Move to trash</button>
                </form>
            </div>
        </div>

        @if(session('preview_url'))<section class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900">Private preview link (expires in 30 minutes): <a class="font-semibold underline" href="{{ session('preview_url') }}" target="_blank">Open preview</a><form class="mt-3 inline" method="POST" action="{{ route($routePrefix.'.pages.preview.revoke', $page) }}">@csrf<button class="font-semibold underline">Revoke preview links</button></form></section>@endif

        <form method="POST" action="{{ route($routePrefix.'.pages.update', $page) }}">
            @include('command-center.cms.pages._form', ['method' => 'PUT'])
        </form>

        @include('command-center.cms.pages._sections')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Revision History</h2>
            <div class="mt-4 space-y-3">
                @forelse ($page->revisions as $revision)
                    <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                        <p class="font-medium text-slate-950 dark:text-white">Revision {{ $revision->revision_number }} - {{ str($revision->status)->headline() }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $revision->created_at->format('d M Y, h:i A') }} by {{ $revision->user?->name ?? 'System' }}</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 dark:text-slate-400">No revisions yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection

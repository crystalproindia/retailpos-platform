@extends('layouts.admin')

@section('title', 'CMS Pages')
@section('page-title', 'CMS Pages')

@section('breadcrumbs')
    <span>/</span>
    <span>CMS</span>
    <span>/</span>
    <span>Pages</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Dynamic Pages</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Create, publish, schedule, revise, and optimize public website pages.</p>
                </div>
                <a href="{{ route('cms.pages.create') }}" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">New page</a>
            </div>

            <form method="GET" action="{{ route('cms.pages.index') }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_180px_180px_auto]">
                <input name="search" value="{{ request('search') }}" placeholder="Search pages" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                    @endforeach
                </select>
                <select name="page_type" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All page types</option>
                    @foreach ($pageTypes as $type)
                        <option value="{{ $type }}" @selected(request('page_type') === $type)>{{ str($type)->headline() }}</option>
                    @endforeach
                </select>
                <select name="trashed" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">Active only</option>
                    <option value="with" @selected(request('trashed') === 'with')>Include trash</option>
                </select>
                <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Filter</button>
            </form>
        </section>

        <form method="POST" action="{{ route('cms.pages.bulk') }}" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center dark:border-slate-800">
                <select name="action" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="publish">Publish</option>
                    <option value="unpublish">Unpublish</option>
                    <option value="delete">Move to trash</option>
                </select>
                <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Apply bulk action</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3"></th>
                            <th class="px-5 py-3">Page</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Revision</th>
                            <th class="px-5 py-3">Updated</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($pages as $page)
                            <tr>
                                <td class="px-5 py-4"><input type="checkbox" name="ids[]" value="{{ $page->id }}" class="rounded border-slate-300"></td>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $page->title }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">/{{ $page->slug }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ str($page->status)->headline() }}</span>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $page->revisions_count ?? $page->revisions()->count() }}</td>
                                <td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ $page->updated_at->format('d M Y') }}</td>
                                <td class="px-5 py-4 text-right">
                                    @if ($page->trashed())
                                        <button formaction="{{ route('cms.pages.restore', $page->id) }}" formmethod="POST" class="text-sm font-semibold text-teal-700 dark:text-teal-300">Restore</button>
                                    @else
                                        <a href="{{ route('cms.pages.edit', $page) }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">No CMS pages found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $pages->links() }}</div>
        </form>
    </div>
@endsection

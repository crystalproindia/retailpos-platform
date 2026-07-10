@extends('layouts.admin')

@section('title', 'Media Library')
@section('page-title', 'Media Library')

@section('breadcrumbs')
    <span>/</span>
    <span>CMS</span>
    <span>/</span>
    <span>Media</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')

        <section class="grid gap-6 xl:grid-cols-[0.8fr_1.2fr]">
            <div class="space-y-6">
                <form method="POST" action="{{ route('cms.media.store') }}" enctype="multipart/form-data" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    @csrf
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Upload Media</h1>
                    <div class="mt-5 space-y-4">
                        <input type="file" name="file" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <input name="name" placeholder="Display name" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <input name="alt_text" placeholder="Alt text" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <select name="folder_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <option value="">No folder</option>
                            @foreach ($folders as $folder)
                                <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Upload</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('cms.media.folders.store') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    @csrf
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Create Folder</h2>
                    <div class="mt-4 space-y-3">
                        <input name="name" placeholder="Folder name" required class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <input name="slug" placeholder="Slug" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <select name="parent_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <option value="">No parent</option>
                            @foreach ($folders as $folder)
                                <option value="{{ $folder->id }}">{{ $folder->name }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Create folder</button>
                    </div>
                </form>
            </div>

            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                    <div>
                        <h1 class="text-xl font-semibold text-slate-950 dark:text-white">File Manager</h1>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Images, SVG, PDFs, video, and downloadable files.</p>
                    </div>
                </div>

                <form method="GET" action="{{ route('cms.media.index') }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_150px_180px_auto]">
                    <input name="search" value="{{ request('search') }}" placeholder="Search media" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <select name="type" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="">All types</option>
                        @foreach (['image', 'svg', 'pdf', 'video', 'file'] as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ str($type)->headline() }}</option>
                        @endforeach
                    </select>
                    <select name="folder_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="">All folders</option>
                        @foreach ($folders as $folder)
                            <option value="{{ $folder->id }}" @selected((string) request('folder_id') === (string) $folder->id)>{{ $folder->name }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:text-slate-200">Filter</button>
                </form>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                    @forelse ($media as $item)
                        <article class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-950 dark:text-white">{{ $item->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ str($item->type)->headline() }} - {{ number_format($item->size / 1024, 1) }} KB</p>
                                </div>
                                <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $item->extension ?: 'file' }}</span>
                            </div>
                            <p class="mt-3 truncate text-xs text-slate-500 dark:text-slate-400">{{ $item->path }}</p>
                            <form method="POST" action="{{ route('cms.media.destroy', $item) }}" class="mt-4">
                                @csrf
                                @method('DELETE')
                                <button class="text-sm font-semibold text-rose-700 dark:text-rose-300">Move to trash</button>
                            </form>
                        </article>
                    @empty
                        <div class="col-span-full rounded-lg border border-dashed border-slate-300 p-8 text-center text-slate-500 dark:border-slate-700 dark:text-slate-400">No media files found.</div>
                    @endforelse
                </div>

                <div class="mt-5">{{ $media->links() }}</div>
            </section>
        </section>
    </div>
@endsection

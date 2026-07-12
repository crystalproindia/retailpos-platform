@extends('layouts.admin')

@section('title', 'Media Library')
@section('page-title', 'Media Library')
@section('breadcrumbs')<span>/</span><span>CMS</span><span>/</span><span>Media</span>@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')
        <section class="cms-panel flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"><div><p class="cms-kicker">Asset library</p><h1 class="mt-2 text-xl font-semibold text-slate-900">Media Library</h1><p class="mt-2 text-sm leading-6 text-slate-600">Keep images, documents, video, and downloadable files organised for content editors.</p></div><span class="cms-chip">{{ $media->total() }} files</span></section>

        <section class="grid gap-6 xl:grid-cols-[0.72fr_1.28fr]">
            <aside class="space-y-6">
                <form method="POST" action="{{ route('cms.media.store') }}" enctype="multipart/form-data" class="cms-panel">@csrf<p class="cms-kicker">Upload</p><h2 class="mt-2 text-lg font-semibold text-slate-900">Add media</h2><div class="mt-5 space-y-4"><label class="cms-control-label">File<input type="file" name="file" required class="mt-2 block w-full"></label><label class="cms-control-label">Display name<input name="name" placeholder="A clear asset name" class="mt-2 block w-full"></label><label class="cms-control-label">Alt text<input name="alt_text" placeholder="Describe the image for readers" class="mt-2 block w-full"></label><label class="cms-control-label">Folder<select name="folder_id" class="mt-2 block w-full"><option value="">No folder</option>@foreach ($folders as $folder)<option value="{{ $folder->id }}">{{ $folder->name }}</option>@endforeach</select></label><button class="cms-button-primary w-full">Upload file</button></div></form>
                <form method="POST" action="{{ route('cms.media.folders.store') }}" class="cms-panel">@csrf<p class="cms-kicker">Organisation</p><h2 class="mt-2 text-lg font-semibold text-slate-900">Create folder</h2><div class="mt-5 space-y-4"><input name="name" placeholder="Folder name" required><input name="slug" placeholder="Folder slug"><select name="parent_id"><option value="">No parent</option>@foreach ($folders as $folder)<option value="{{ $folder->id }}">{{ $folder->name }}</option>@endforeach</select><button class="cms-button-secondary w-full">Create folder</button></div></form>
            </aside>

            <section class="cms-panel">
                <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"><div><p class="cms-kicker">File manager</p><h2 class="mt-2 text-lg font-semibold text-slate-900">Browse assets</h2></div></div>
                <form method="GET" action="{{ route('cms.media.index') }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_150px_180px_auto]"><input name="search" value="{{ request('search') }}" placeholder="Search files"><select name="type"><option value="">All types</option>@foreach (['image', 'svg', 'pdf', 'video', 'file'] as $type)<option value="{{ $type }}" @selected(request('type') === $type)>{{ str($type)->headline() }}</option>@endforeach</select><select name="folder_id"><option value="">All folders</option>@foreach ($folders as $folder)<option value="{{ $folder->id }}" @selected((string) request('folder_id') === (string) $folder->id)>{{ $folder->name }}</option>@endforeach</select><button class="cms-button-secondary">Filter</button></form>
                <div class="mt-6 grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
                    @forelse ($media as $item)
                        @php($fileUrl = \Illuminate\Support\Facades\Storage::disk($item->disk ?: config('filesystems.default'))->url($item->path))
                        <article class="overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:border-teal-200 hover:shadow-[0_10px_24px_rgb(13_148_136_/_0.08)]">
                            <div class="grid aspect-[16/9] place-items-center bg-slate-100">
                                @if (in_array($item->type, ['image', 'svg']))
                                    <img src="{{ $fileUrl }}" alt="{{ $item->alt_text ?: $item->name }}" class="h-full w-full object-cover">
                                @else
                                    <span class="grid size-14 place-items-center rounded-xl bg-white text-sm font-bold text-slate-400 shadow-sm">{{ str($item->extension ?: $item->type)->upper()->substr(0, 4) }}</span>
                                @endif
                            </div>
                            <div class="p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate font-semibold text-slate-900">{{ $item->name }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $item->folder?->name ?: 'Unfiled' }}</p></div><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600">{{ str($item->type)->headline() }}</span></div><p class="mt-4 line-clamp-2 text-sm leading-5 text-slate-600">{{ $item->alt_text ?: 'No alt text added yet.' }}</p><div class="mt-4 flex flex-wrap items-center gap-2"><button type="button" data-copy-text="{{ $fileUrl }}" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-teal-200 hover:text-teal-800">Copy URL</button><span class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs text-slate-500">{{ number_format($item->size / 1024, 1) }} KB</span><span class="rounded-lg bg-slate-50 px-2.5 py-1.5 text-xs text-slate-500">Used in: —</span></div><div class="mt-4 flex items-center justify-between"><button type="button" disabled title="Replace workflow is reserved for the existing media lifecycle" class="text-sm font-semibold text-slate-400">Replace</button><form method="POST" action="{{ route('cms.media.destroy', $item) }}">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-700 hover:text-rose-800">Move to trash</button></form></div></div>
                        </article>
                    @empty
                        <div class="col-span-full rounded-xl border border-dashed border-slate-300 bg-slate-50 p-10 text-center text-sm text-slate-500">No media files match this view.</div>
                    @endforelse
                </div>
                <div class="mt-6">{{ $media->links() }}</div>
            </section>
        </section>
    </div>
@endsection

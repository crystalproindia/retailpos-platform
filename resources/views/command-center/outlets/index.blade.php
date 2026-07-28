@extends('layouts.admin')
@section('title', 'Outlets')
@section('page-title', 'Outlets')
@section('breadcrumbs') <span>/</span><span>Outlets</span> @endsection
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <section class="flex flex-col gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900">
        <div><p class="text-sm font-semibold text-slate-950 dark:text-white">Your retail locations</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Products and customers stay shared. Stock, operational work, and team access are managed per outlet.</p></div>
        <a href="{{ route('settings.outlets.create') }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950">Add outlet</a>
    </section>
    @if ($limit !== null)<p class="text-sm text-slate-500 dark:text-slate-400">{{ $outlets->where('is_active', true)->count() }} of {{ $limit }} active outlets are available on the current plan.</p>@endif
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($outlets as $outlet)
            <article class="rounded-lg border {{ $outlet->is_active ? 'border-slate-200 bg-white' : 'border-slate-200 bg-slate-50' }} p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4"><div><div class="flex flex-wrap items-center gap-2"><h2 class="font-semibold text-slate-950 dark:text-white">{{ $outlet->name }}</h2>@if($outlet->is_primary)<span class="rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-800 dark:bg-teal-950 dark:text-teal-200">Default</span>@endif @unless($outlet->is_active)<span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-300">Archived</span>@endunless</div><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $outlet->code }}@if($outlet->city) · {{ $outlet->city }}@endif</p></div><a href="{{ route('settings.outlets.edit', $outlet) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200">Edit</a></div>
                <dl class="mt-5 grid grid-cols-2 gap-4 text-sm"><div><dt class="text-slate-500">Team access</dt><dd class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $outlet->active_assignments_count }}</dd></div><div><dt class="text-slate-500">Invoice prefix</dt><dd class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $outlet->invoice_prefix ?: 'Company default' }}</dd></div></dl>
            </article>
        @empty
            <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900">No outlets are configured yet.</div>
        @endforelse
    </div>
</div>
@endsection

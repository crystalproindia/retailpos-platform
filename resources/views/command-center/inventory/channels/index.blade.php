@extends('layouts.admin')

@section('title', 'Sales Channels')
@section('page-title', 'Sales Channels')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Channels</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <div class="mb-4 flex justify-between gap-3"><a href="{{ route('inventory.channel-mappings.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Product mappings</a><a href="{{ route('inventory.channels.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">New channel</a></div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse($channels as $channel)
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3"><div><h2 class="font-semibold">{{ $channel->name }}</h2><p class="font-mono text-xs text-slate-500">{{ $channel->code }} / {{ $channel->type }}</p></div><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $channel->sync_enabled ? 'bg-teal-100 text-teal-700' : 'bg-slate-100 text-slate-600' }}">{{ $channel->sync_enabled ? 'Sync ready' : 'Manual' }}</span></div>
                <p class="mt-3 text-sm text-slate-500">{{ $channel->description ?: 'Internal channel foundation. No external API is connected in Phase 3.' }}</p>
                <div class="mt-4 flex items-center justify-between text-sm"><span>{{ $channel->mappings_count }} mappings</span><a href="{{ route('inventory.channels.edit', $channel) }}" class="font-semibold text-teal-700">Edit</a></div>
            </article>
        @empty
            <div class="rounded-lg border border-slate-200 bg-white p-8 text-center text-slate-500">No sales channels yet.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $channels->links() }}</div>
@endsection

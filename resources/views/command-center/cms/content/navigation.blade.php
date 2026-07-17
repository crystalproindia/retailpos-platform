@extends('layouts.admin')

@section('title', 'Website Navigation')
@section('page-title', 'Website Navigation')

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')
        <section class="cms-panel"><p class="cms-kicker">Website Content</p><h1 class="mt-2 text-2xl font-semibold text-slate-900">Navigation builder</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Manage the links visitors use to move around your website. You can use internal paths such as <code>/pricing</code> or secure external links.</p></section>

        <section class="cms-panel"><div><h2 class="text-lg font-semibold text-slate-900">Add navigation link</h2><p class="mt-1 text-sm text-slate-600">Start with the links visitors need most. Nested links are ready for a future mega menu.</p></div><form method="POST" action="{{ route('cms.content.navigation.store') }}" class="mt-5 grid gap-4 md:grid-cols-2">@csrf @include('command-center.cms.content.navigation-fields', ['item' => null])<div class="md:col-span-2"><button class="rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">Add link</button></div></form></section>

        <section class="space-y-4">@forelse($items as $item)<article class="cms-panel"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="font-semibold text-slate-900">{{ $item->label }}</p><p class="mt-1 text-sm text-slate-500">{{ $locations[$item->location] }} · {{ $item->is_enabled ? 'Visible to visitors' : 'Hidden from visitors' }}</p></div><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $item->is_enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $item->is_enabled ? 'Enabled' : 'Disabled' }}</span></div><form method="POST" action="{{ route('cms.content.navigation.update', $item) }}" class="mt-5 grid gap-4 md:grid-cols-2">@csrf @method('PUT') @include('command-center.cms.content.navigation-fields', ['item' => $item])<div class="md:col-span-2"><button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Save link</button></div></form></article>@empty<div class="cms-subtle-panel text-center text-sm text-slate-500">No navigation links have been added yet. Add a header link above to get started.</div>@endforelse</section>
    </div>
@endsection

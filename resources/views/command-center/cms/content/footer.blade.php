@extends('layouts.admin')

@section('title', 'Website Footer')
@section('page-title', 'Website Footer')

@section('content')
    <div class="space-y-6">
        @include('command-center.cms.partials.nav')
        <section class="cms-panel"><p class="cms-kicker">Website Content</p><h1 class="mt-2 text-2xl font-semibold text-slate-900">Footer manager</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Keep company information, useful links, and the final call to action together in one simple place.</p></section>
        <section class="cms-panel"><h2 class="text-lg font-semibold text-slate-900">Add footer block</h2><p class="mt-1 text-sm text-slate-600">Each block becomes a clear area in your website footer.</p><form method="POST" action="{{ route('cms.content.footer.store') }}" class="mt-5">@csrf @include('command-center.cms.content.footer-fields', ['block' => null])<button class="mt-5 rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700">Add footer block</button></form></section>
        <section class="space-y-4">@forelse($blocks as $block)<article class="cms-panel"><div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold text-slate-900">{{ str($block->block_key)->replace('_', ' ')->headline() }}</h2><p class="mt-1 text-sm text-slate-500">{{ $block->is_enabled ? 'Visible to visitors' : 'Saved but hidden from visitors' }}</p></div></div><form method="POST" action="{{ route('cms.content.footer.update', $block) }}" class="mt-5">@csrf @method('PUT') @include('command-center.cms.content.footer-fields', ['block' => $block])<button class="mt-5 rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Save footer block</button></form></article>@empty<div class="cms-subtle-panel text-center text-sm text-slate-500">No footer blocks have been added. Start with a company description or contact block.</div>@endforelse</section>
    </div>
@endsection

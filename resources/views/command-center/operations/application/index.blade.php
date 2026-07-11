@extends('layouts.admin')

@section('title', 'Application Info')
@section('page-title', 'Application Info')

@section('breadcrumbs')
    <span>/</span><span>Operations</span><span>/</span><span>Application Info</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.operations.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Application Info</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Safe runtime and deployment metadata. Secrets, credentials, keys, and webhook secrets are never displayed.</p>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($info as $item)
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $item['label'] }}</p>
                    <p class="mt-2 break-all text-lg font-semibold text-slate-950 dark:text-white">{{ $item['value'] }}</p>
                </div>
            @endforeach
        </section>
    </div>
@endsection

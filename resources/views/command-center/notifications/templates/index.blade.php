@extends('layouts.admin')

@section('title', 'Notification Templates')
@section('page-title', 'Notification Templates')

@section('breadcrumbs')
    <span>/</span><span>Notifications</span><span>/</span><span>Templates</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.notifications.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Templates</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">CMS-ready notification copy for database and email delivery. Use variables like <span class="font-mono">{{ '{{ lead_title }}' }}</span> and <span class="font-mono">{{ '{{ subject }}' }}</span>.</p>
        </section>

        <section class="space-y-4">
            @forelse ($templates as $template)
                <form method="POST" action="{{ route('notifications.templates.update', $template->id) }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    @csrf
                    @method('PUT')
                    <div class="flex flex-col justify-between gap-3 md:flex-row md:items-start">
                        <div>
                            <h2 class="font-semibold text-slate-950 dark:text-white">{{ $template->name }}</h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $eventOptions[$template->event_key]['name'] ?? $template->event_key }} · {{ str($template->channel)->headline() }} · v{{ $template->version }}</p>
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked($template->is_active)>
                            <span>Active</span>
                        </label>
                    </div>
                    <div class="mt-4 grid gap-3">
                        <input name="subject" value="{{ old('subject', $template->subject) }}" placeholder="Subject" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <textarea name="body" rows="4" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('body', $template->body) }}</textarea>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Save template</button>
                    </div>
                </form>
            @empty
                <div class="rounded-lg border border-slate-200 bg-white px-5 py-12 text-center text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">No notification templates seeded yet.</div>
            @endforelse

            {{ $templates->links() }}
        </section>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'Webhooks')
@section('page-title', 'Webhook Endpoints')

@section('breadcrumbs')
    <span>/</span><span>Notifications</span><span>/</span><span>Webhooks</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.notifications.partials.nav')

        <section class="grid gap-6 lg:grid-cols-[1fr_380px]">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Outbound webhooks</h1>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Secure, signed webhook delivery for allowed company events. Secrets are encrypted and never displayed after generation.</p>
                <form method="GET" action="{{ route('notifications.webhooks.index') }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_160px_auto]">
                    <input name="search" value="{{ request('search') }}" placeholder="Search endpoint name" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="">All statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="disabled" @selected(request('status') === 'disabled')>Disabled</option>
                    </select>
                    <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Filter</button>
                </form>
            </div>

            @can('notifications.webhooks.manage')
                <form method="POST" action="{{ route('notifications.webhooks.store') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    @csrf
                    <h2 class="font-semibold text-slate-950 dark:text-white">New endpoint</h2>
                    <div class="mt-4 space-y-3">
                        <input name="name" value="{{ old('name') }}" placeholder="Endpoint name" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <input name="url" value="{{ old('url') }}" placeholder="https://example.com/webhook" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <div class="max-h-40 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-800">
                            @foreach ($eventOptions as $key => $definition)
                                @if ($definition['webhook_enabled'] ?? false)
                                    <label class="flex items-start gap-2">
                                        <input type="checkbox" name="subscribed_events[]" value="{{ $key }}" class="mt-1 rounded border-slate-300">
                                        <span><span class="block font-medium">{{ $definition['name'] }}</span><span class="block text-xs text-slate-500">{{ $key }}</span></span>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300">
                            <span>Enable endpoint</span>
                        </label>
                        <button class="w-full rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Create endpoint</button>
                    </div>
                </form>
            @endcan
        </section>

        @if ($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="space-y-4">
            @forelse ($endpoints as $endpoint)
                <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h2 class="font-semibold text-slate-950 dark:text-white">{{ $endpoint->name }}</h2>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $endpoint->is_active ? 'bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-200' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $endpoint->is_active ? 'Active' : 'Disabled' }}</span>
                            </div>
                            <p class="mt-2 truncate text-sm text-slate-500 dark:text-slate-400">{{ $endpoint->url }}</p>
                            <p class="mt-2 text-xs text-slate-400">Events: {{ collect($endpoint->subscribed_events)->join(', ') }}</p>
                        </div>
                        @can('notifications.webhooks.manage')
                            <div class="flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('notifications.webhooks.toggle', $endpoint->id) }}">
                                    @csrf
                                    <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">{{ $endpoint->is_active ? 'Disable' : 'Enable' }}</button>
                                </form>
                                <form method="POST" action="{{ route('notifications.webhooks.rotate-secret', $endpoint->id) }}">
                                    @csrf
                                    <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Rotate secret</button>
                                </form>
                            </div>
                        @endcan
                    </div>

                    @can('notifications.webhooks.manage')
                        <form method="POST" action="{{ route('notifications.webhooks.update', $endpoint->id) }}" class="mt-5 grid gap-3 lg:grid-cols-[180px_1fr_auto]">
                            @csrf
                            @method('PUT')
                            <input name="name" value="{{ $endpoint->name }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            <input name="url" value="{{ $endpoint->url }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @foreach ($endpoint->subscribed_events as $eventKey)
                                <input type="hidden" name="subscribed_events[]" value="{{ $eventKey }}">
                            @endforeach
                            <input type="hidden" name="is_active" value="{{ $endpoint->is_active ? 1 : 0 }}">
                            <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Save</button>
                        </form>
                    @endcan
                </article>
            @empty
                <div class="rounded-lg border border-slate-200 bg-white px-5 py-12 text-center text-sm text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">No webhook endpoints configured.</div>
            @endforelse

            {{ $endpoints->links() }}
        </section>
    </div>
@endsection

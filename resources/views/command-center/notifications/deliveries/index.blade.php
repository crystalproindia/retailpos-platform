@extends('layouts.admin')

@section('title', 'Delivery Log')
@section('page-title', 'Notification Delivery Log')

@section('breadcrumbs')
    <span>/</span><span>Notifications</span><span>/</span><span>Delivery Log</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.notifications.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Delivery Log</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Track in-app, email, future channel, and webhook delivery attempts.</p>

            <form method="GET" action="{{ route('notifications.deliveries.index') }}" class="mt-5 grid gap-3 lg:grid-cols-[1fr_140px_160px_180px_auto]">
                <select name="event_key" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All events</option>
                    @foreach ($eventOptions as $key => $definition)
                        <option value="{{ $key }}" @selected(request('event_key') === $key)>{{ $definition['name'] }}</option>
                    @endforeach
                </select>
                <select name="channel" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">Channels</option>
                    @foreach (['database', 'email', 'whatsapp', 'sms', 'push'] as $channel)
                        <option value="{{ $channel }}" @selected(request('channel') === $channel)>{{ str($channel)->headline() }}</option>
                    @endforeach
                </select>
                <select name="status" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">Statuses</option>
                    @foreach (['pending', 'queued', 'sending', 'delivered', 'failed', 'unsupported'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>
                    @endforeach
                </select>
                <select name="user_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All users</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) request('user_id') === (string) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Filter</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="font-semibold text-slate-950 dark:text-white">Notification deliveries</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Event</th>
                            <th class="px-5 py-3">Channel</th>
                            <th class="px-5 py-3">Recipient</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Attempts</th>
                            <th class="px-5 py-3">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($deliveries as $delivery)
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $eventOptions[$delivery->event_key]['name'] ?? str($delivery->event_key)->headline() }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $delivery->event_key }}</p>
                                </td>
                                <td class="px-5 py-4">{{ str($delivery->channel)->headline() }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $delivery->user?->name ?? $delivery->recipient }}</td>
                                <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ str($delivery->status)->headline() }}</span></td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $delivery->attempt_count }}</td>
                                <td class="px-5 py-4 text-slate-500 dark:text-slate-400">{{ $delivery->updated_at->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">No notification deliveries found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $deliveries->links() }}</div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                <h2 class="font-semibold text-slate-950 dark:text-white">Webhook deliveries</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Endpoint</th>
                            <th class="px-5 py-3">Event</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">HTTP</th>
                            <th class="px-5 py-3">Attempts</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($webhookDeliveries as $delivery)
                            <tr>
                                <td class="px-5 py-4 font-medium text-slate-950 dark:text-white">{{ $delivery->endpoint?->name ?? 'Deleted endpoint' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $delivery->event_key }}</td>
                                <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ str($delivery->status)->headline() }}</span></td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $delivery->response_code ?? 'n/a' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $delivery->attempt_count }}</td>
                                <td class="px-5 py-4 text-right">
                                    @can('notifications.webhooks.retry')
                                        <form method="POST" action="{{ route('notifications.webhooks.deliveries.retry', $delivery->id) }}">
                                            @csrf
                                            <button class="text-sm font-semibold text-teal-700 dark:text-teal-300">Retry</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">No webhook deliveries found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $webhookDeliveries->links() }}</div>
        </section>
    </div>
@endsection

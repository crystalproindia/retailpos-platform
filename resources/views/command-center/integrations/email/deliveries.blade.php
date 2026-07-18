@extends('layouts.admin')
@section('title', 'Email Delivery Log')
@section('page-title', 'Email Delivery Log')
@section('breadcrumbs')<span>/</span><span>Settings</span><span>/</span><span>Email deliveries</span>@endsection
@section('content')
<div class="space-y-6">
    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-start justify-between gap-4"><div><h1 class="text-xl font-semibold text-slate-950 dark:text-white">Email delivery log</h1><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Status and retry history without credentials or raw provider responses.</p></div><a href="{{ route('settings.integrations.email.index') }}" class="text-sm font-semibold text-teal-700 dark:text-teal-300">Email settings</a></div>
        <form class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-5"><input name="recipient" value="{{ request('recipient') }}" placeholder="Recipient" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><select name="status" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="">All statuses</option>@foreach(['queued','sending','sent','failed','skipped_not_configured','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->headline() }}</option>@endforeach</select><input name="template" value="{{ request('template') }}" placeholder="Template" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><input type="date" name="from" value="{{ request('from') }}" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold">Filter</button></form>
    </section>
    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"><div class="overflow-x-auto"><table class="min-w-full divide-y divide-slate-200 text-sm"><thead class="bg-slate-50 text-left text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Recipient</th><th class="px-5 py-3">Subject</th><th class="px-5 py-3">Template</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Attempts</th><th class="px-5 py-3">Updated</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y divide-slate-100">
    @forelse($deliveries as $delivery)
        <tr><td class="px-5 py-4">{{ $delivery->recipient_name ?: $delivery->recipient }}<p class="text-xs text-slate-500">{{ $delivery->recipient_name ? $delivery->recipient : '' }}</p></td><td class="px-5 py-4">{{ $delivery->subject ?: str($delivery->event_key)->headline() }}</td><td class="px-5 py-4 text-slate-600">{{ $delivery->template_key ?: 'System notification' }}</td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">{{ str($delivery->status)->headline() }}</span>@if($delivery->failure_reason)<p class="mt-1 max-w-xs text-xs text-rose-700">{{ $delivery->failure_reason }}</p>@endif</td><td class="px-5 py-4">{{ $delivery->attempt_count }}</td><td class="px-5 py-4 text-slate-500">{{ $delivery->updated_at->format('d M Y H:i') }}</td><td class="px-5 py-4 text-right">
            @can('email.deliveries.retry')
                @if($delivery->status === 'failed')<form method="POST" action="{{ route('settings.email-deliveries.retry', $delivery) }}">@csrf<button class="text-sm font-semibold text-teal-700">Retry</button></form>
                @elseif(in_array($delivery->status, ['pending','queued']))<form method="POST" action="{{ route('settings.email-deliveries.cancel', $delivery) }}">@csrf<button class="text-sm font-semibold text-rose-700">Cancel</button></form>
                @endif
            @endcan
        </td></tr>
    @empty<tr><td colspan="7" class="px-5 py-12 text-center text-slate-500">No email deliveries match these filters.</td></tr>@endforelse
    </tbody></table></div><div class="border-t border-slate-200 px-5 py-4">{{ $deliveries->links() }}</div></section>
</div>
@endsection

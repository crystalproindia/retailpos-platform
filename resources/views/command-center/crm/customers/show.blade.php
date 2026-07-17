@extends('layouts.admin')

@section('title', $customer->company_name)
@section('page-title', $customer->company_name)

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span>
    <a href="{{ route('crm.customers.index') }}" class="hover:text-slate-950 dark:hover:text-white">Customers</a>
    <span>/</span><span>{{ $customer->customer_code }}</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                <div>
                    <p class="text-sm font-medium text-teal-700 dark:text-teal-300">{{ $customer->customer_code }}</p>
                    <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $customer->company_name }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ $customer->display_name }}
                        @if ($customer->business_type)
                            · {{ $customer->business_type }}
                        @endif
                    </p>
                </div>
                <span class="inline-flex w-fit rounded-full bg-sky-100 px-3 py-1.5 text-sm font-semibold text-sky-800 dark:bg-sky-950 dark:text-sky-200">{{ $customer->status?->label() }}</span>
            </div>
        </section>
        <div class="flex flex-wrap justify-end gap-3"><a href="{{ route('crm.proformas.create-from-customer', $customer) }}" class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Create Proforma Invoice</a>@can('crm.support.create')<a href="{{ route('crm.support.tickets.create', ['customer' => $customer->id]) }}" class="rounded-lg border border-sky-300 px-4 py-2 text-sm font-semibold text-sky-700 transition hover:bg-sky-50 dark:border-sky-800 dark:text-sky-300 dark:hover:bg-sky-950/30">Create Support Ticket</a>@endcan @if($customer->activeOnboarding)<a href="{{ route('crm.onboarding.show', $customer->activeOnboarding) }}" class="rounded-lg border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-700 dark:border-teal-800 dark:text-teal-300">Open Onboarding · {{ $customer->activeOnboarding->progress_percent }}%</a>@else<form method="POST" action="{{ route('crm.customers.onboarding.start', $customer) }}">@csrf<button class="rounded-lg border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-700 dark:border-teal-800 dark:text-teal-300">Start Onboarding</button></form>@endif</div>

        <section class="grid gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Account details</h2>
                <dl class="mt-5 space-y-3 text-sm">
                    @foreach (['Email' => $customer->email, 'Phone' => $customer->phone, 'Location' => collect([$customer->city, $customer->state, $customer->country])->filter()->join(', '), 'Tax Number' => $customer->tax_number, 'Stores' => $customer->number_of_stores, 'Source' => $customer->source, 'Converted' => $customer->converted_at?->format('d M Y, h:i A')] as $label => $value)
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800 dark:text-slate-100">{{ $value ?: 'Not recorded' }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if ($customer->billing_address)
                    <div class="mt-5 border-t border-slate-100 pt-5 dark:border-slate-800">
                        <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Billing address</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $customer->billing_address }}</p>
                    </div>
                @endif
                @if ($customer->notes)
                    <div class="mt-5 border-t border-slate-100 pt-5 dark:border-slate-800">
                        <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Internal notes</p>
                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-slate-200">{{ $customer->notes }}</p>
                    </div>
                @endif
            </article>

            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Primary contact</h2>
                <div class="mt-5 space-y-3">
                    @forelse ($customer->contacts as $contact)
                        <div class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                            <div class="flex items-start justify-between gap-3">
                                <div><p class="font-semibold text-slate-950 dark:text-white">{{ $contact->name }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $contact->designation ?? 'Primary contact' }}</p></div>
                                @if ($contact->is_primary)<span class="rounded-full bg-teal-100 px-2.5 py-1 text-xs font-semibold text-teal-800 dark:bg-teal-950 dark:text-teal-200">Primary</span>@endif
                            </div>
                            <p class="mt-3 text-sm text-slate-700 dark:text-slate-200">{{ $contact->email ?? 'No email recorded' }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $contact->phone ?? 'No phone recorded' }}</p>
                        </div>
                    @empty
                        <p class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No contacts have been recorded.</p>
                    @endforelse
                </div>
            </article>
        </section>

        @can('crm.customers.portal.manage')
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"><div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Portal Access</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Invite customer contacts with a one-time secure link. No portal password or automatic email is used.</p></div><span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800 dark:bg-sky-950 dark:text-sky-200">{{ $customer->portalUsers->where('status', 'active')->count() }} active</span></div>
                @if (session('portalInviteUrl'))
                    <div class="mt-4 rounded-lg border border-teal-200 bg-teal-50 p-4 dark:border-teal-900 dark:bg-teal-950/30"><p class="text-sm font-semibold text-teal-900 dark:text-teal-100">Secure access link ready</p><div class="mt-3 flex flex-col gap-2 sm:flex-row"><input readonly value="{{ session('portalInviteUrl') }}" class="min-w-0 flex-1 rounded-lg border border-teal-200 bg-white px-3 py-2 text-xs text-slate-700 dark:border-teal-900 dark:bg-slate-950 dark:text-slate-200"><button type="button" data-copy-text="{{ session('portalInviteUrl') }}" class="rounded-lg bg-teal-700 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-800">Copy link</button></div></div>
                @endif
                <form method="POST" action="{{ route('crm.customers.portal-users.invite', $customer) }}" class="mt-5 grid gap-3 md:grid-cols-3">@csrf<input name="name" value="{{ old('name', $customer->display_name) }}" placeholder="Customer name" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"><input name="email" type="email" value="{{ old('email', $customer->email) }}" placeholder="Email address" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"><div class="flex gap-3"><input name="phone" value="{{ old('phone', $customer->phone) }}" placeholder="Phone" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950"><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Create link</button></div></form>
                @error('name')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror @error('email')<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
                <div class="mt-5 space-y-3">@forelse($customer->portalUsers as $portalUser)<div class="flex flex-col justify-between gap-3 rounded-lg border border-slate-200 p-4 sm:flex-row sm:items-center dark:border-slate-800"><div><p class="font-semibold text-slate-950 dark:text-white">{{ $portalUser->name }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $portalUser->email }} · {{ $portalUser->last_login_at?->format('d M Y, h:i A') ?? 'No sign-in yet' }}</p></div><div class="flex items-center gap-2"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $portalUser->status === 'suspended' ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200' : 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200' }}">{{ str($portalUser->status)->headline() }}</span><form method="POST" action="{{ route('crm.customers.portal-users.link', [$customer, $portalUser]) }}">@csrf<button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">New link</button></form><form method="POST" action="{{ route('crm.customers.portal-users.status', [$customer, $portalUser]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $portalUser->status === 'suspended' ? 'active' : 'suspended' }}"><button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800">{{ $portalUser->status === 'suspended' ? 'Reactivate' : 'Suspend' }}</button></form></div></div>@empty<p class="rounded-lg border border-dashed border-slate-300 px-4 py-7 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No customer portal contact has been invited.</p>@endforelse</div>
            </section>
        @endcan

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ([['title' => 'Stores', 'copy' => 'Multi-store management will appear here.'], ['title' => 'Subscriptions', 'copy' => 'Customer subscriptions will appear here.'], ['title' => 'Invoices', 'copy' => 'CRM invoice history will appear here.']] as $area)
                <article class="rounded-lg border border-dashed border-slate-300 bg-white p-5 dark:border-slate-700 dark:bg-slate-900"><h2 class="text-base font-semibold text-slate-950 dark:text-white">{{ $area['title'] }}</h2><p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">{{ $area['copy'] }}</p><p class="mt-4 text-xs font-semibold uppercase text-slate-400 dark:text-slate-500">Coming soon</p></article>
            @endforeach
        </section>

        @if ($supportSummary)
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"><div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Support Tickets</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $supportSummary['open'] }} open ticket{{ $supportSummary['open'] === 1 ? '' : 's' }} for this customer.</p></div><a href="{{ route('crm.support.tickets.index', ['search' => $customer->company_name]) }}" class="text-sm font-semibold text-sky-700 hover:text-sky-900 dark:text-sky-300">View all tickets</a></div>
                <div class="mt-4 grid gap-3 lg:grid-cols-2">@forelse ($supportSummary['recent'] as $ticket)<a href="{{ route('crm.support.tickets.show', $ticket) }}" class="support-ticket-row rounded-lg border border-slate-200 p-4 dark:border-slate-800"><div class="flex items-start justify-between gap-3"><div><p class="text-xs font-semibold text-slate-500">{{ $ticket->ticket_number }}</p><p class="mt-1 font-semibold text-slate-950 dark:text-white">{{ $ticket->subject }}</p></div><span class="support-status-badge rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $ticket->status->label() }}</span></div><p class="mt-2 text-xs text-slate-500">{{ $ticket->updated_at->diffForHumans() }} · {{ $ticket->assignee?->name ?? 'Unassigned' }}</p></a>@empty<p class="rounded-lg border border-dashed border-slate-300 px-4 py-7 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No support tickets yet. Create one when this customer needs help.</p>@endforelse</div>
            </section>
        @endif

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between"><div><h2 class="text-base font-semibold text-slate-950 dark:text-white">Service Requests</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Additional services requested by this customer through the portal.</p></div></div>
            <div class="mt-4 grid gap-3 lg:grid-cols-2">@forelse($customer->serviceRequests->filter(fn ($lead) => $lead->source?->slug === 'customer-portal') as $request)<a href="{{ route('crm.leads.show', $request) }}" class="rounded-lg border border-slate-200 p-4 transition hover:border-sky-300 hover:bg-sky-50/50 dark:border-slate-800 dark:hover:border-sky-900 dark:hover:bg-sky-950/20"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-950 dark:text-white">{{ $request->metadata['service_category'] ?? $request->title }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ \Illuminate\Support\Str::limit($request->description, 110) }}</p></div><span class="rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-800 dark:bg-sky-950 dark:text-sky-200">{{ $request->priority?->label() }}</span></div><p class="mt-3 text-xs text-slate-500">{{ $request->created_at->format('d M Y, h:i A') }}</p></a>@empty<p class="rounded-lg border border-dashed border-slate-300 px-4 py-7 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No additional service requests have been submitted.</p>@endforelse</div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Linked lead</h2>
                @if ($customer->lead)
                    <a href="{{ route('crm.leads.show', $customer->lead) }}" class="mt-5 block rounded-lg border border-slate-200 p-4 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"><p class="font-semibold text-slate-950 dark:text-white">{{ $customer->lead->title }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $customer->lead->status?->name ?? 'No status' }}</p></a>
                @else
                    <p class="mt-5 rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No lead is linked to this customer.</p>
                @endif
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Accepted quotation</h2>
                @if ($customer->quotation)
                    <a href="{{ route('crm.quotations.show', $customer->quotation) }}" class="mt-5 block rounded-lg border border-slate-200 p-4 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800"><p class="font-semibold text-slate-950 dark:text-white">{{ $customer->quotation->quotation_number }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $customer->quotation->currency }} {{ number_format((float) $customer->quotation->grand_total, 2) }}</p></a>
                @else
                    <p class="mt-5 rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No accepted quotation was linked during conversion.</p>
                @endif
            </article>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Conversion activity</h2>
            <div class="mt-5 space-y-3">
                @forelse ($customer->auditLogs as $audit)
                    <div class="rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-800"><p class="text-sm font-medium text-slate-950 dark:text-white">{{ $audit->description }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $audit->created_at?->format('d M Y, h:i A') }} by {{ $audit->user?->name ?? 'System' }}</p></div>
                @empty
                    <p class="rounded-lg border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">No conversion activity has been recorded yet.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection

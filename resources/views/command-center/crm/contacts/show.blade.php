@extends('layouts.admin')

@section('title', $contact->fullName())
@section('page-title', $contact->fullName())

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Contacts</span><span>/</span><span>{{ $contact->id }}</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950 dark:text-white">{{ $contact->fullName() }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $contact->job_title ?? 'CRM contact' }} · {{ $contact->crmCompany?->name ?? 'Unlinked' }}</p>
                </div>
                <a href="{{ route('crm.contacts.edit', $contact) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Edit</a>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Details</h2>
                <dl class="mt-5 space-y-3 text-sm">
                    @foreach ([
                        'Email' => $contact->email,
                        'Phone' => $contact->phone,
                        'Preferred Method' => $contact->preferred_contact_method?->label(),
                        'Owner' => $contact->assignedUser?->name,
                        'Primary' => $contact->is_primary ? 'Yes' : 'No',
                    ] as $label => $value)
                        <div class="flex justify-between gap-4 border-b border-slate-100 pb-3 dark:border-slate-800">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-800 dark:text-slate-100">{{ $value ?? 'N/A' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Related Leads</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($contact->leads as $lead)
                        <a href="{{ route('crm.leads.show', $lead) }}" class="block rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-800">
                            <p class="font-medium text-slate-950 dark:text-white">{{ $lead->title }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $lead->status?->name }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">No related leads.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
@endsection

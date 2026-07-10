@extends('layouts.admin')

@section('title', $crmCompany->name)
@section('page-title', $crmCompany->name)

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Companies</span><span>/</span><span>{{ $crmCompany->id }}</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-start">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-950 dark:text-white">{{ $crmCompany->name }}</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $crmCompany->industry ?? 'CRM account' }} · {{ $crmCompany->assignedUser?->name ?? 'Unassigned' }}</p>
                </div>
                <a href="{{ route('crm.companies.edit', $crmCompany) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Edit</a>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Contacts</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($crmCompany->contacts as $contact)
                        <a href="{{ route('crm.contacts.show', $contact) }}" class="block rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-800">
                            <p class="font-medium text-slate-950 dark:text-white">{{ $contact->fullName() }}</p>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $contact->email ?? $contact->phone ?? 'No contact detail' }}</p>
                        </a>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">No contacts linked.</p>
                    @endforelse
                </div>
            </article>
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 xl:col-span-2">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">Related Leads</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($crmCompany->leads as $lead)
                        <a href="{{ route('crm.leads.show', $lead) }}" class="flex items-center justify-between gap-4 rounded-lg border border-slate-200 px-4 py-3 dark:border-slate-800">
                            <span class="font-medium text-slate-950 dark:text-white">{{ $lead->title }}</span>
                            <span class="text-sm text-slate-500 dark:text-slate-400">{{ $lead->status?->name }}</span>
                        </a>
                    @empty
                        <p class="text-sm text-slate-500 dark:text-slate-400">No leads linked.</p>
                    @endforelse
                </div>
            </article>
        </section>
    </div>
@endsection

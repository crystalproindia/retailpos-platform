@extends('layouts.admin')

@section('title', 'CRM Contacts')
@section('page-title', 'CRM Contacts')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Contacts</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Contacts</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Manage CRM people, associations, owners, notes, and related leads.</p>
                </div>
                <a href="{{ route('crm.contacts.create') }}" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">New contact</a>
            </div>
            <form method="GET" action="{{ route('crm.contacts.index') }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_220px_160px_auto]">
                <input name="search" value="{{ request('search') }}" placeholder="Search contacts" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <select name="crm_company_id" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">All companies</option>
                    @foreach ($crmCompanies as $crmCompany)
                        <option value="{{ $crmCompany->id }}" @selected((int) request('crm_company_id') === $crmCompany->id)>{{ $crmCompany->name }}</option>
                    @endforeach
                </select>
                <select name="trashed" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">Active</option>
                    <option value="with" @selected(request('trashed') === 'with')>With trash</option>
                </select>
                <button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Filter</button>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950 dark:text-slate-400">
                        <tr>
                            <th class="px-5 py-3">Contact</th>
                            <th class="px-5 py-3">Company</th>
                            <th class="px-5 py-3">Owner</th>
                            <th class="px-5 py-3">Method</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($contacts as $contact)
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $contact->fullName() }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $contact->email ?? $contact->phone ?? 'No contact detail' }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $contact->crmCompany?->name ?? 'Unlinked' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $contact->assignedUser?->name ?? 'Unassigned' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $contact->preferred_contact_method?->label() }}</td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('crm.contacts.show', $contact) }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">No CRM contacts found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $contacts->links() }}</div>
        </section>
    </div>
@endsection

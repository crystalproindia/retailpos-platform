@extends('layouts.admin')

@section('title', 'CRM Companies')
@section('page-title', 'CRM Companies')

@section('breadcrumbs')
    <span>/</span><span>CRM</span><span>/</span><span>Companies</span>
@endsection

@section('content')
    <div class="space-y-6">
        @include('command-center.crm.partials.nav')

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <div>
                    <h1 class="text-xl font-semibold text-slate-950 dark:text-white">CRM Companies</h1>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Manage account records, owners, related contacts, leads, and timelines.</p>
                </div>
                <a href="{{ route('crm.companies.create') }}" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">New company</a>
            </div>
            <form method="GET" action="{{ route('crm.companies.index') }}" class="mt-5 grid gap-3 md:grid-cols-[1fr_180px_180px_auto]">
                <input name="search" value="{{ request('search') }}" placeholder="Search companies" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                <input name="industry" value="{{ request('industry') }}" placeholder="Industry" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
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
                            <th class="px-5 py-3">Company</th>
                            <th class="px-5 py-3">Owner</th>
                            <th class="px-5 py-3">Contacts</th>
                            <th class="px-5 py-3">Pipeline</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($crmCompanies as $crmCompany)
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-slate-950 dark:text-white">{{ $crmCompany->name }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $crmCompany->industry ?? $crmCompany->email ?? 'No industry set' }}</p>
                                </td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $crmCompany->assignedUser?->name ?? 'Unassigned' }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">{{ $crmCompany->contacts->count() }}</td>
                                <td class="px-5 py-4 text-slate-600 dark:text-slate-300">₹{{ number_format((float) $crmCompany->estimated_value, 0) }}</td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('crm.companies.show', $crmCompany) }}" class="text-sm font-semibold text-slate-700 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500 dark:text-slate-400">No CRM companies found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-4 dark:border-slate-800">{{ $crmCompanies->links() }}</div>
        </section>
    </div>
@endsection

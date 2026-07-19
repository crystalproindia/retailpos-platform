@extends('layouts.admin')

@section('title', 'POS Registers')
@section('page-title', 'POS Registers')

@section('content')
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h1 class="text-xl font-semibold text-slate-950 dark:text-white">Registers and cash sessions</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Open one session per register before assigning it to a counter sale. Closing records expected cash and variance without changing historical sales.</p>
        </section>

        @can('pos.registers.manage')
            <form method="POST" action="{{ route('pos.registers.store') }}" class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-4 dark:border-slate-800 dark:bg-slate-900">
                @csrf
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">
                    Branch
                    <select name="branch_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2">
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($branch->id === auth()->user()->branch_id)>{{ $branch->name }} · {{ $branch->code }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Register code<input name="code" required maxlength="48" placeholder="COUNTER-1" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Register name<input name="name" required maxlength="255" placeholder="Front Counter" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Receipt prefix<input name="receipt_prefix" maxlength="24" value="POS" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2"></label>
                <div class="sm:col-span-2 xl:col-span-4"><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Create register</button></div>
            </form>
        @endcan

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-950">
                        <tr><th class="p-4">Register</th><th class="p-4">Branch</th><th class="p-4">Session</th><th class="p-4">Cash</th><th class="p-4 text-right">Action</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse($registers as $register)
                            <tr>
                                <td class="p-4"><p class="font-semibold text-slate-950 dark:text-white">{{ $register->name }}</p><p class="mt-1 text-xs text-slate-500">{{ $register->code }} · {{ $register->receipt_prefix }}</p></td>
                                <td class="p-4">{{ $register->branch?->name }}</td>
                                <td class="p-4">
                                    @if($register->currentSession)
                                        <span class="rounded-full bg-teal-100 px-2.5 py-1 text-xs font-semibold text-teal-800 dark:bg-teal-950 dark:text-teal-200">Open since {{ $register->currentSession->opened_at?->format('d M, h:i A') }}</span>
                                    @else
                                        <span class="text-slate-500">No open session</span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    @if($register->currentSession)
                                        {{ number_format((float) $register->currentSession->opening_cash, 2) }} opening
                                    @endif
                                </td>
                                <td class="p-4 text-right">
                                    @if($register->currentSession)
                                        @can('pos.sessions.close')
                                            <form method="POST" action="{{ route('pos.registers.sessions.close', $register->currentSession) }}" class="inline-flex gap-2">
                                                @csrf
                                                <input name="closing_cash" required type="number" min="0" step="0.01" placeholder="Closing cash" class="w-28 rounded border border-slate-300 px-2 py-1.5 text-xs">
                                                <button class="text-xs font-semibold text-rose-700">Close</button>
                                            </form>
                                        @endcan
                                    @else
                                        @can('pos.sessions.open')
                                            <form method="POST" action="{{ route('pos.registers.open', $register) }}" class="inline-flex gap-2">
                                                @csrf
                                                <input name="opening_cash" type="number" min="0" step="0.01" value="0" class="w-20 rounded border border-slate-300 px-2 py-1.5 text-xs">
                                                <button class="text-xs font-semibold text-teal-700">Open</button>
                                            </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-10 text-center text-slate-500">No POS registers have been created for this company.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection

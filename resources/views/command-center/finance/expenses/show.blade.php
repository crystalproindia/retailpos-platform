@extends('layouts.admin')

@section('title', 'Expense')
@section('page-title', 'Expense')

@section('content')
    @include('command-center.finance.partials.nav')

    <div class="mx-auto max-w-4xl space-y-5">
        <header class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm font-semibold text-teal-700 dark:text-teal-300">{{ str($entry->classification_snapshot)->headline() }}</p>
                <h1 class="text-2xl font-semibold text-slate-950 dark:text-white">{{ $entry->currency }} {{ number_format((float) $entry->amount, 2) }}</h1>
                <p class="text-sm text-slate-500">{{ ucfirst($entry->status) }} · {{ $entry->transaction_date?->format('d M Y') }}</p>
            </div>

            @if ($entry->receipt_path)
                <a href="{{ route('finance.expenses.receipt', $entry) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">View receipt</a>
            @endif
        </header>

        <section class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 sm:grid-cols-2 dark:border-slate-800 dark:bg-slate-900">
            <div><p class="text-xs uppercase text-slate-500">Category</p><p class="font-semibold">{{ $entry->category->name }}</p></div>
            <div><p class="text-xs uppercase text-slate-500">Outlet</p><p class="font-semibold">{{ $entry->branch?->name ?? 'Company-wide' }}</p></div>
            <div><p class="text-xs uppercase text-slate-500">Payee</p><p>{{ $entry->payee ?: '—' }}</p></div>
            <div><p class="text-xs uppercase text-slate-500">Payment / reference</p><p>{{ $entry->payment_method ?: '—' }} {{ $entry->reference ? '· '.$entry->reference : '' }}</p></div>
            <div class="sm:col-span-2">
                <p class="text-xs uppercase text-slate-500">Description</p>
                <p>{{ $entry->description }}</p>
                @if ($entry->notes)
                    <p class="mt-2 text-sm text-slate-500">{{ $entry->notes }}</p>
                @endif
            </div>
        </section>

        @if ($entry->status === 'draft')
            @can('finance.expenses.post')
                <form method="post" action="{{ route('finance.expenses.post', $entry) }}">
                    @csrf
                    <button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Post expense</button>
                </form>
            @endcan
        @endif

        @if ($entry->status === 'posted')
            @can('finance.expenses.reverse')
                <form method="post" action="{{ route('finance.expenses.reverse', $entry) }}" class="rounded-lg border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/70 dark:bg-rose-950/30">
                    @csrf
                    <label class="block text-sm font-semibold">
                        Reverse expense
                        <span class="font-normal text-slate-500">This creates a linked correction; it does not delete the record.</span>
                        <input required name="reason" placeholder="Reason" class="mt-2 w-full rounded-lg border-rose-200 bg-white dark:border-rose-900 dark:bg-slate-950">
                    </label>
                    <button class="mt-3 rounded-lg bg-rose-700 px-4 py-2 text-sm font-semibold text-white">Reverse expense</button>
                </form>
            @endcan
        @endif
    </div>
@endsection

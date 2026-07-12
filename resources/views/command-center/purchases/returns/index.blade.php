@extends('layouts.admin')

@section('title', 'Purchase Returns')
@section('page-title', 'Purchase Returns')
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>Returns</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <div class="mb-4 flex justify-end">
        <a href="{{ route('purchases.returns.create') }}" class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">New return</a>
    </div>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    <tr><th class="px-5 py-3">Return</th><th class="px-5 py-3">Supplier</th><th class="px-5 py-3">Warehouse</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Reason</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($returns as $return)
                        <tr>
                            <td class="px-5 py-3 font-medium"><a href="{{ route('purchases.returns.show', $return) }}">{{ $return->return_number }}</a></td>
                            <td class="px-5 py-3 text-slate-500">{{ $return->supplier?->name }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ $return->warehouse?->name }}</td>
                            <td class="px-5 py-3">{{ str($return->status->value)->headline() }}</td>
                            <td class="px-5 py-3">{{ $return->reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No purchase returns found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-4 dark:border-slate-800">{{ $returns->links() }}</div>
    </section>
@endsection

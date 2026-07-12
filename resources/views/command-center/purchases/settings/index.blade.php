@extends('layouts.admin')

@section('title', 'Purchase Settings')
@section('page-title', 'Purchase Settings')
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>Settings</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <form method="POST" action="{{ route('purchases.settings.update') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        @csrf
        @method('PUT')
        <div class="grid gap-4 md:grid-cols-4">
            <label class="text-sm font-medium">PO prefix<input name="po_prefix" value="{{ old('po_prefix', $settings->po_prefix) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required></label>
            <label class="text-sm font-medium">PR prefix<input name="pr_prefix" value="{{ old('pr_prefix', $settings->pr_prefix) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required></label>
            <label class="text-sm font-medium">GRN prefix<input name="grn_prefix" value="{{ old('grn_prefix', $settings->grn_prefix) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required></label>
            <label class="text-sm font-medium">Return prefix<input name="return_prefix" value="{{ old('return_prefix', $settings->return_prefix) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required></label>
        </div>
        <div class="mt-6 grid gap-4 md:grid-cols-2">
            <label class="flex items-center gap-2 text-sm"><input name="require_purchase_request_approval" type="checkbox" value="1" @checked($settings->require_purchase_request_approval) class="rounded"> Require purchase request approval</label>
            <label class="flex items-center gap-2 text-sm"><input name="require_po_approval" type="checkbox" value="1" @checked($settings->require_po_approval) class="rounded"> Require PO approval</label>
            <label class="flex items-center gap-2 text-sm"><input name="require_return_approval" type="checkbox" value="1" @checked($settings->require_return_approval) class="rounded"> Require return approval</label>
            <label class="flex items-center gap-2 text-sm"><input name="allow_receive_without_po" type="checkbox" value="1" @checked($settings->allow_receive_without_po) class="rounded"> Allow receiving without PO</label>
            <label class="flex items-center gap-2 text-sm"><input name="auto_create_pr_from_reorder" type="checkbox" value="1" @checked($settings->auto_create_pr_from_reorder) class="rounded"> Auto create PR from reorder</label>
            <label class="flex items-center gap-2 text-sm"><input name="default_tax_inclusive" type="checkbox" value="1" @checked($settings->default_tax_inclusive) class="rounded"> Default tax inclusive</label>
        </div>
        <label class="mt-6 block text-sm font-medium">Default payment terms
            <input name="default_payment_terms" value="{{ old('default_payment_terms', $settings->default_payment_terms) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
        </label>
        <div class="mt-6 flex justify-end">
            <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Save settings</button>
        </div>
    </form>
@endsection

@extends('layouts.admin')
@section('title', $outlet ? 'Edit outlet' : 'Add outlet')
@section('page-title', $outlet ? 'Edit outlet' : 'Add outlet')
@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <form method="POST" action="{{ $outlet ? route('settings.outlets.update', $outlet) : route('settings.outlets.store') }}" class="space-y-6">@csrf @if($outlet) @method('PUT') @endif
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="text-base font-semibold text-slate-950 dark:text-white">Outlet details</h2><p class="mt-1 text-sm text-slate-500">Use a stable code. It is used for operations and cannot be changed after creation.</p>
            @if($errors->any())
                <div role="alert" aria-live="polite" class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-200">
                    <p class="font-semibold">Please review the outlet details.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium">Display name<input name="name" value="{{ old('name', $outlet?->name) }}" required class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block text-sm font-medium">
                    Outlet code
                    @if($outlet)
                        <input value="{{ $outlet->code }}" disabled class="mt-1 w-full rounded-lg border-slate-200 bg-slate-100 text-slate-500 dark:border-slate-700 dark:bg-slate-800">
                    @else
                        <input name="code" value="{{ old('code') }}" required placeholder="CBE-01" aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}" @if($errors->has('code')) aria-describedby="outlet-code-error" @endif class="mt-1 w-full rounded-lg border-slate-300 uppercase dark:border-slate-700 dark:bg-slate-950">
                        @error('code')<span id="outlet-code-error" class="mt-1 block text-xs font-medium text-rose-700 dark:text-rose-300">{{ $message }}</span>@enderror
                    @endif
                </label>
                <label class="block text-sm font-medium">Legal or invoice name<input name="legal_name" value="{{ old('legal_name', $outlet?->legal_name) }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block text-sm font-medium">Phone<input name="phone" value="{{ old('phone', $outlet?->phone) }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block text-sm font-medium">Email<input name="email" type="email" value="{{ old('email', $outlet?->email) }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block text-sm font-medium">GSTIN <span class="font-normal text-slate-500">(format only)</span><input name="tax_number" value="{{ old('tax_number', $outlet?->tax_number) }}" maxlength="15" class="mt-1 w-full rounded-lg border-slate-300 uppercase dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block text-sm font-medium">Invoice prefix<input name="invoice_prefix" value="{{ old('invoice_prefix', $outlet?->invoice_prefix) }}" placeholder="CBE" class="mt-1 w-full rounded-lg border-slate-300 uppercase dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block text-sm font-medium">Receipt prefix<input name="receipt_prefix" value="{{ old('receipt_prefix', $outlet?->receipt_prefix) }}" placeholder="CBE-POS" class="mt-1 w-full rounded-lg border-slate-300 uppercase dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block text-sm font-medium">City<input name="city" value="{{ old('city', $outlet?->city) }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block text-sm font-medium">State<input name="state" value="{{ old('state', $outlet?->state) }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block text-sm font-medium">Postal code<input name="postal_code" value="{{ old('postal_code', $outlet?->postal_code) }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block text-sm font-medium">Country<input name="country" value="{{ old('country', $outlet?->country ?? 'India') }}" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="block text-sm font-medium sm:col-span-2">Address<textarea name="address" rows="3" class="mt-1 w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">{{ old('address', $outlet?->address) }}</textarea></label>
            </div>
            <div class="mt-6 flex justify-end"><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">{{ $outlet ? 'Save outlet' : 'Create outlet' }}</button></div>
        </section>
    </form>
    @if($outlet)
        <section class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"><h2 class="text-base font-semibold text-slate-950 dark:text-white">Team assignments</h2><p class="mt-1 text-sm text-slate-500">Assign a default working outlet for each team member. Administrators retain company-wide access.</p>
            @can('outlets.assign')<form method="POST" action="{{ route('settings.outlets.assignments.store', $outlet) }}" class="mt-5 flex flex-col gap-3 sm:flex-row">@csrf<select name="user_id" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-950">@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->role->label() }}</option>@endforeach</select><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1"> Default working outlet</label><button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold dark:border-slate-700">Assign user</button></form>@endcan
            <div class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">@forelse($assignments as $assignment)<div class="flex items-center justify-between py-3 text-sm"><span>{{ $assignment->user->name }}</span><span class="text-slate-500">{{ $assignment->is_default ? 'Default working outlet' : 'Assigned' }}</span></div>@empty<p class="py-4 text-sm text-slate-500">No users are assigned yet.</p>@endforelse</div>
        </section>
        <section class="flex flex-wrap items-start gap-3">
            <form method="POST" action="{{ route('settings.outlets.make-default', $outlet) }}">@csrf<button {{ !$outlet->is_active || $outlet->is_primary ? 'disabled' : '' }} class="min-h-11 rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2 disabled:opacity-50 dark:border-slate-700">Make default</button></form>
            @if($outlet->is_active)
                <details class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100">
                    <summary class="min-h-6 cursor-pointer font-semibold focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:ring-offset-2">Archive outlet</summary>
                    <p class="mt-2 max-w-sm">This removes the outlet from new working-context selections. Historical records remain available.</p>
                    <form method="POST" action="{{ route('settings.outlets.archive', $outlet) }}" class="mt-3">@csrf<button class="min-h-11 rounded-lg border border-rose-400 bg-white px-4 py-2 text-sm font-semibold text-rose-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-600 focus-visible:ring-offset-2 dark:bg-rose-950 dark:text-rose-100">Confirm archive</button></form>
                </details>
            @else
                <form method="POST" action="{{ route('settings.outlets.restore', $outlet) }}">@csrf<button class="min-h-11 rounded-lg border border-teal-300 px-4 py-2 text-sm font-semibold text-teal-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-600 focus-visible:ring-offset-2">Restore outlet</button></form>
            @endif
        </section>
    @endif
</div>
@endsection

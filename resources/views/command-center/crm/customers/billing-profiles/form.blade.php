@extends('layouts.admin')

@section('title', $profile->exists ? 'Edit billing profile' : 'Add billing profile')
@section('page-title', $profile->exists ? 'Edit billing profile' : 'Add billing profile')

@section('content')
    <div class="mx-auto max-w-3xl space-y-6">
        @include('command-center.crm.partials.nav')
        <form method="POST" action="{{ $profile->exists ? route('crm.customers.billing-profiles.update', [$customer, $profile]) : route('crm.customers.billing-profiles.store', $customer) }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @csrf @if($profile->exists) @method('PUT') @endif
            <h1 class="text-xl font-semibold text-slate-950 dark:text-white">{{ $profile->exists ? 'Edit' : 'Add' }} billing profile</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">This legal identity can be selected on new invoices. Documents keep their own saved snapshot.</p>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                @foreach (['profile_name' => 'Profile name', 'legal_name' => 'Legal name', 'trade_name' => 'Trade name', 'tax_number' => 'GSTIN', 'email' => 'Email', 'phone' => 'Phone', 'city' => 'City', 'state' => 'State', 'postal_code' => 'Postal code', 'country' => 'Country'] as $field => $label)
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $label }}<input name="{{ $field }}" @if(in_array($field,['email'])) type="email" @endif value="{{ old($field, $profile->{$field}) }}" @if(in_array($field,['profile_name','legal_name'])) required @endif class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">@error($field)<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
                @endforeach
                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Status<select name="status" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="active" @selected(old('status', $profile->status ?? 'active') === 'active')>Active</option><option value="inactive" @selected(old('status', $profile->status) === 'inactive')>Inactive</option></select></label>
                <label class="flex items-center gap-3 self-end rounded-lg border border-slate-200 p-3 text-sm font-medium dark:border-slate-800"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $profile->is_primary))> Primary billing profile</label>
            </div>
            <label class="mt-4 block text-sm font-medium text-slate-700 dark:text-slate-200">Billing address<textarea name="billing_address" rows="4" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('billing_address', $profile->billing_address) }}</textarea>@error('billing_address')<span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>@enderror</label>
            <div class="mt-6 flex justify-end gap-3"><a href="{{ route('crm.customers.show', $customer) }}" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold dark:border-slate-700">Cancel</a><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save billing profile</button></div>
        </form>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'White-label settings')
@section('page-title', 'White-label settings')
@section('breadcrumbs')<span>/</span><a href="{{ route('account.subscription.index') }}">Subscription</a><span>/</span><span>White-label</span>@endsection

@section('content')
    <form method="POST" action="{{ route('account.subscription.white-label.update') }}" class="mx-auto max-w-5xl space-y-6">
        @csrf @method('PUT')
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div><h2 class="font-semibold text-slate-950 dark:text-white">Your application brand</h2><p class="mt-1 text-sm text-slate-500">These settings prepare the Command Center for your brand. RetailPOS remains the default until values are saved.</p></div>
            <div class="mt-5 grid gap-5 md:grid-cols-2">
                <label class="text-sm font-medium">Display name<input name="display_name" required maxlength="255" value="{{ old('display_name', $values['display_name']) }}" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="text-sm font-medium">Support email<input name="support_email" type="email" value="{{ old('support_email', $values['support_email']) }}" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="text-sm font-medium">Logo<select name="logo_media_id" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950"><option value="">Use RetailPOS default</option>@foreach($media as $item)<option value="{{ $item->id }}" @selected(old('logo_media_id', $values['logo_media_id']) == $item->id)>{{ $item->name }}</option>@endforeach</select></label>
                <label class="text-sm font-medium">Favicon<select name="favicon_media_id" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950"><option value="">Use RetailPOS default</option>@foreach($media as $item)<option value="{{ $item->id }}" @selected(old('favicon_media_id', $values['favicon_media_id']) == $item->id)>{{ $item->name }}</option>@endforeach</select></label>
                <label class="text-sm font-medium">Primary colour<input name="primary_color" type="color" value="{{ old('primary_color', $values['primary_color']) }}" class="mt-1 block h-10 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="text-sm font-medium">Secondary colour<input name="secondary_color" type="color" value="{{ old('secondary_color', $values['secondary_color']) }}" class="mt-1 block h-10 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="text-sm font-medium md:col-span-2">Email sender name<input name="email_sender_name" maxlength="255" value="{{ old('email_sender_name', $values['email_sender_name']) }}" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label>
            </div>
            <label class="mt-5 flex items-center gap-3 text-sm"><input type="hidden" name="show_powered_by" value="0"><input type="checkbox" name="show_powered_by" value="1" @checked(old('show_powered_by', $values['show_powered_by'])) class="rounded border-slate-300">Show the RetailPOS powered-by reference where it is available.</label>
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="font-semibold text-slate-950 dark:text-white">Custom domain readiness</h2><p class="mt-1 text-sm text-slate-500">Record the domain’s current state. DNS, SSL, and routing are intentionally not automated in this foundation.</p>
            <div class="mt-5 grid gap-5 md:grid-cols-2"><label class="text-sm font-medium">Requested domain<input name="custom_domain" maxlength="255" placeholder="app.example.com" value="{{ old('custom_domain', $values['custom_domain']) }}" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950"></label><label class="text-sm font-medium">Status<select name="custom_domain_status" class="mt-1 block w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">@foreach(['not_configured','pending','verified','active','failed'] as $status)<option value="{{ $status }}" @selected(old('custom_domain_status', $values['custom_domain_status']) === $status)>{{ str($status)->headline() }}</option>@endforeach</select></label></div>
        </section>
        <div class="flex justify-end"><button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save white-label settings</button></div>
    </form>
@endsection

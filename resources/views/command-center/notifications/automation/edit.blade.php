@extends('layouts.admin')

@section('title', 'Notifications & Automation')
@section('page-title', 'Notifications & Automation')

@section('breadcrumbs')
    <span>/</span><a href="{{ route('notifications.index') }}" class="hover:text-slate-950 dark:hover:text-white">Notifications</a><span>/</span><span>Automation</span>
@endsection

@section('content')
    @php
        $toggle = 'size-5 rounded border-slate-300 text-teal-600 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-950';
        $input = 'mt-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900 shadow-sm focus:border-teal-500 focus:ring-4 focus:ring-teal-500/15 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
    @endphp
    <div class="mx-auto max-w-6xl space-y-6">
        @include('command-center.notifications.partials.nav')

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 p-5 dark:border-slate-800 sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-teal-700 dark:text-teal-300">Company settings</p>
                        <h1 class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">Helpful alerts, without the noise</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">RetailPOS checks authoritative business records on schedule and alerts the right people only when a condition is new, worsens, or returns after recovery.</p>
                    </div>
                    <div class="rounded-lg border px-3 py-2 text-sm {{ $emailConfiguration['configured'] ? 'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-900 dark:bg-teal-950/40 dark:text-teal-200' : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200' }}">
                        <span class="font-semibold">Email:</span> {{ $emailConfiguration['configured'] ? 'Ready' : 'Not configured' }}
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('notifications.automation.update') }}" class="p-5 sm:p-6">
                @csrf
                @method('PUT')

                <div class="grid gap-5 lg:grid-cols-2">
                    <section class="rounded-lg border border-slate-200 p-5 dark:border-slate-800" aria-labelledby="automation-inventory">
                        <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-lg bg-teal-50 text-teal-700 dark:bg-teal-950/50 dark:text-teal-300"><x-icon name="inventory" class="size-5" /></span><div><h2 id="automation-inventory" class="font-semibold text-slate-950 dark:text-white">Inventory</h2><p class="text-sm text-slate-500 dark:text-slate-400">Act before availability affects a sale.</p></div></div>
                        <div class="mt-5 space-y-4">
                            @foreach ([['low_stock_enabled','Low-stock alerts','When available stock falls below its configured minimum.'],['out_of_stock_enabled','Out-of-stock alerts','A stronger alert when available stock reaches zero.'],['reorder_enabled','Reorder reminders','Uses Inventory Intelligence recommendations without creating a PO.']] as [$name,$label,$help])
                                <label class="flex items-start gap-3"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $setting->{$name})) class="{{ $toggle }}"><span><span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $label }}</span><span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $help }}</span></span></label>
                            @endforeach
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 p-5 dark:border-slate-800" aria-labelledby="automation-sales">
                        <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300"><x-icon name="orders" class="size-5" /></span><div><h2 id="automation-sales" class="font-semibold text-slate-950 dark:text-white">Sales documents</h2><p class="text-sm text-slate-500 dark:text-slate-400">Keep due dates and valid offers visible.</p></div></div>
                        <div class="mt-5 space-y-4">
                            @foreach ([['payment_reminders_enabled','Payment reminders','Uses the current remaining invoice balance after payments and credit notes.'],['quotation_expiry_enabled','Quotation expiry','Skips converted, cancelled, rejected, and terminal quotations.'],['proforma_expiry_enabled','Proforma expiry','Tracks active sent and partially-paid proformas.']] as [$name,$label,$help])
                                <label class="flex items-start gap-3"><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $setting->{$name})) class="{{ $toggle }}"><span><span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $label }}</span><span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $help }}</span></span></label>
                            @endforeach
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Remind before due (days)<input name="payment_before_due_days" value="{{ old('payment_before_due_days', implode(', ', $setting->payment_before_due_days ?: [3])) }}" class="{{ $input }}"><span class="mt-1 block text-xs font-normal text-slate-500">Example: 3, 7</span></label>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Overdue stages (days)<input name="payment_overdue_days" value="{{ old('payment_overdue_days', implode(', ', $setting->payment_overdue_days ?: [1,7,30])) }}" class="{{ $input }}"><span class="mt-1 block text-xs font-normal text-slate-500">Example: 1, 7, 30</span></label>
                            </div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Document expiry notice<input type="number" min="1" max="30" name="document_expiry_notice_days" value="{{ old('document_expiry_notice_days', $setting->document_expiry_notice_days) }}" class="{{ $input }}"><span class="mt-1 block text-xs font-normal text-slate-500">Days before a quotation or proforma expiry becomes visible.</span></label>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 p-5 dark:border-slate-800" aria-labelledby="automation-delivery">
                        <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"><x-icon name="bell" class="size-5" /></span><div><h2 id="automation-delivery" class="font-semibold text-slate-950 dark:text-white">Delivery</h2><p class="text-sm text-slate-500 dark:text-slate-400">In-app alerts always remain available.</p></div></div>
                        <div class="mt-5 space-y-4">
                            <label class="flex items-start gap-3"><input type="checkbox" name="purchase_reminders_enabled" value="1" @checked(old('purchase_reminders_enabled', $setting->purchase_reminders_enabled)) class="{{ $toggle }}"><span><span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Purchase attention reminders</span><span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">Includes deterministic reorder recommendations and overdue expected receipts.</span></span></label>
                            <label class="flex items-start gap-3"><input type="checkbox" name="internal_email_enabled" value="1" @checked(old('internal_email_enabled', $setting->internal_email_enabled)) class="{{ $toggle }}"><span><span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Email internal alerts</span><span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">Queues email through the existing company SMTP configuration. In-app alerts continue if email is unavailable.</span></span></label>
                            <label class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-900 dark:bg-amber-950/20"><input type="checkbox" name="customer_payment_emails_enabled" value="1" @checked(old('customer_payment_emails_enabled', $setting->customer_payment_emails_enabled)) class="{{ $toggle }}"><span><span class="block text-sm font-semibold text-amber-950 dark:text-amber-100">Email customers about payment due dates</span><span class="mt-1 block text-xs leading-5 text-amber-800 dark:text-amber-200">Off by default. Enable only after reviewing your reminder policy and SMTP identity.</span></span></label>
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/60"><div class="flex items-center justify-between gap-3"><span class="text-sm font-semibold text-slate-700 dark:text-slate-200">WhatsApp</span><span class="rounded-full bg-slate-200 px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">Planned</span></div><p class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">Provider-ready channel support is reserved for a later phase. No WhatsApp messages or credentials are active.</p></div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 p-5 dark:border-slate-800" aria-labelledby="automation-owner">
                        <div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-lg bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300"><x-icon name="analytics" class="size-5" /></span><div><h2 id="automation-owner" class="font-semibold text-slate-950 dark:text-white">Owner summary</h2><p class="text-sm text-slate-500 dark:text-slate-400">A deterministic snapshot that works without AI.</p></div></div>
                        <div class="mt-5 space-y-4">
                            <label class="flex items-start gap-3"><input type="checkbox" name="daily_summary_enabled" value="1" @checked(old('daily_summary_enabled', $setting->daily_summary_enabled)) class="{{ $toggle }}"><span><span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Daily business summary</span><span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">Sent once at the selected local hour.</span></span></label>
                            <label class="flex items-start gap-3"><input type="checkbox" name="weekly_summary_enabled" value="1" @checked(old('weekly_summary_enabled', $setting->weekly_summary_enabled)) class="{{ $toggle }}"><span><span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">Weekly business summary</span><span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">Sent on Monday at the selected local hour.</span></span></label>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Preferred local time<input type="time" name="summary_time" value="{{ old('summary_time', substr((string) $setting->summary_time, 0, 5)) }}" class="{{ $input }}"></label>
                                <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Timezone<select name="timezone" class="{{ $input }}">@foreach(timezone_identifiers_list() as $timezone)<option value="{{ $timezone }}" @selected(old('timezone', $setting->timezone) === $timezone)>{{ $timezone }}</option>@endforeach</select></label>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                    <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">Scheduler reruns and queue retries use deterministic fingerprints, so unchanged conditions do not produce duplicate alerts.</p>
                    <button class="rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 active:translate-y-px dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Save automation settings</button>
                </div>
            </form>
        </section>
    </div>
@endsection

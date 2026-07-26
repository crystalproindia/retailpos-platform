@extends('layouts.admin')

@section('title', 'Invoice Reminders')
@section('page-title', 'Invoice Reminders')
@section('breadcrumbs')<span>/</span><a href="{{ route('sales.invoices.index') }}">Invoices</a><span>/</span><span>Reminders</span>@endsection

@section('content')
    @php
        $rules = $setting->rules->keyBy(fn ($rule) => $rule->stage->value);
        $automaticStages = collect($stages)->filter->isAutomatic();
        $timing = function (int $days): string {
            if ($days < 0) return abs($days).' day'.(abs($days) === 1 ? '' : 's').' before the due date';
            if ($days === 0) return 'On the due date';
            return $days.' day'.($days === 1 ? '' : 's').' after the due date';
        };
    @endphp
    <div class="mx-auto max-w-6xl space-y-6">
        <section class="flex flex-col gap-4 border-b border-slate-200 pb-6 dark:border-slate-800 sm:flex-row sm:items-start sm:justify-between">
            <div><p class="text-sm font-semibold text-teal-700 dark:text-teal-300">Collections</p><h1 class="mt-1 text-2xl font-semibold text-slate-950 dark:text-white">Invoice reminders</h1><p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 dark:text-slate-400">Set the timing and customer-friendly wording for unpaid sales invoice reminders. Automatic reminders are off until your team enables them.</p></div>
            @can('integrations.email.view')
                <a href="{{ route('settings.integrations.email.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Email delivery settings</a>
            @endcan
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach (['queued_today' => 'Queued today', 'sent_today' => 'Sent today', 'failed' => 'Failed reminders', 'upcoming_due_soon' => 'Upcoming due soon', 'overdue_awaiting' => 'Overdue awaiting', 'invalid_email' => 'Invalid email addresses'] as $key => $label)
                <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"><p class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white">{{ $summary[$key] }}</p></div>
            @endforeach
        </section>

        <form method="POST" action="{{ route('sales.invoices.reminders.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h2 class="font-semibold text-slate-950 dark:text-white">Automatic reminders</h2><p class="mt-1 text-sm leading-6 text-slate-500 dark:text-slate-400">When enabled, RetailPOS evaluates eligible issued invoices using your company timezone and queues at most one qualifying reminder stage.</p></div><label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-700 dark:text-slate-200"><input type="hidden" name="automatic_enabled" value="0"><input type="checkbox" name="automatic_enabled" value="1" @checked(old('automatic_enabled', $setting->automatic_enabled))>Enable automatic reminders</label></div>
                <label class="mt-5 block max-w-sm text-sm font-medium text-slate-700 dark:text-slate-300">Minimum cooldown between reminders<input type="number" name="minimum_cooldown_hours" min="1" max="168" value="{{ old('minimum_cooldown_hours', $setting->minimum_cooldown_hours) }}" class="mt-2 block w-full"><span class="mt-1 block text-xs font-normal leading-5 text-slate-500">Hours to wait before another reminder can be sent for the same invoice. The recommended setting is 24 hours.</span></label>
            </section>

            @foreach ($automaticStages as $stage)
                @php($rule = $rules[$stage->value])
                <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <input type="hidden" name="rules[{{ $stage->value }}][stage]" value="{{ $stage->value }}">
                    <div class="flex flex-col gap-4 border-b border-slate-100 pb-4 dark:border-slate-800 sm:flex-row sm:items-start sm:justify-between"><div><p class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">Reminder stage</p><h2 class="mt-1 text-lg font-semibold text-slate-950 dark:text-white">{{ $stage->label() }}</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Example trigger: {{ $tenantToday->copy()->addDays($rule->offset_days)->format('d M Y') }}.</p></div><label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-700 dark:text-slate-200"><input type="hidden" name="rules[{{ $stage->value }}][enabled]" value="0"><input type="checkbox" name="rules[{{ $stage->value }}][enabled]" value="1" @checked(old('rules.'.$stage->value.'.enabled', $rule->enabled))>Enable this stage</label></div>
                    <div class="mt-5 grid gap-5 lg:grid-cols-[12rem_minmax(0,1fr)]"><label class="text-sm font-medium text-slate-700 dark:text-slate-300">Timing<input type="number" name="rules[{{ $stage->value }}][offset_days]" min="-90" max="180" value="{{ old('rules.'.$stage->value.'.offset_days', $rule->offset_days) }}" class="mt-2 block w-full"><span class="mt-1 block text-xs font-normal leading-5 text-slate-500">{{ $timing($rule->offset_days) }}. Negative values are before the due date.</span></label><div class="space-y-4"><label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email subject<input name="rules[{{ $stage->value }}][subject]" maxlength="180" value="{{ old('rules.'.$stage->value.'.subject', $rule->subject) }}" class="mt-2 block w-full"><span class="mt-1 block text-xs font-normal text-slate-500">Use plain text. Supported placeholders: <code>{invoice_number}</code>, <code>{due_date}</code>, <code>{outstanding_balance}</code>, and <code>{business_name}</code>.</span></label><label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Opening message<textarea name="rules[{{ $stage->value }}][intro_message]" maxlength="4000" rows="4" class="mt-2 block w-full">{{ old('rules.'.$stage->value.'.intro_message', $rule->intro_message) }}</textarea><span class="mt-1 block text-xs font-normal text-slate-500">This appears above the invoice details. HTML and scripts are not accepted.</span></label></div></div>
                    <div class="mt-5 flex flex-wrap gap-5 border-t border-slate-100 pt-4 text-sm dark:border-slate-800"><label class="inline-flex items-center gap-3 text-slate-700 dark:text-slate-200"><input type="hidden" name="rules[{{ $stage->value }}][attach_pdf]" value="0"><input type="checkbox" name="rules[{{ $stage->value }}][attach_pdf]" value="1" @checked(old('rules.'.$stage->value.'.attach_pdf', $rule->attach_pdf))>Attach the active invoice PDF</label><label class="inline-flex items-center gap-3 text-slate-700 dark:text-slate-200"><input type="hidden" name="rules[{{ $stage->value }}][include_secure_link]" value="0"><input type="checkbox" name="rules[{{ $stage->value }}][include_secure_link]" value="1" @checked(old('rules.'.$stage->value.'.include_secure_link', $rule->include_secure_link))>Include secure View Invoice link</label></div>
                </section>
            @endforeach

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-sm text-slate-500 dark:text-slate-400">Sender and reply-to details come from Email delivery settings. Saving reminder rules never sends an email.</p><button class="rounded-lg bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950">Save reminder settings</button></div>
        </form>

        <form method="POST" action="{{ route('sales.invoices.reminders.settings.restore') }}" onsubmit="return confirm('Restore the recommended reminder timing and email wording? Automatic reminders will remain disabled.')">@csrf<button class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Restore recommended defaults</button></form>
    </div>
@endsection

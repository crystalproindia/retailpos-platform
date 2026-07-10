@php
    $methodValue = old('preferred_contact_method', $contact->preferred_contact_method instanceof \App\Enums\Crm\PreferredContactMethod ? $contact->preferred_contact_method->value : ($contact->preferred_contact_method ?? 'phone'));
    $selectedTags = collect(old('tag_ids', $contact->exists ? $contact->tags->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
@endphp

@csrf
@if (($method ?? 'POST') !== 'POST')
    @method($method)
@endif

<div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
    <section class="space-y-5 rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="first_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">First Name</label>
                <input id="first_name" name="first_name" value="{{ old('first_name', $contact->first_name) }}" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
            <div>
                <label for="last_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Last Name</label>
                <input id="last_name" name="last_name" value="{{ old('last_name', $contact->last_name) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
            <input name="job_title" value="{{ old('job_title', $contact->job_title) }}" placeholder="Job title" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            <input name="email" type="email" value="{{ old('email', $contact->email) }}" placeholder="Email" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            <input name="phone" value="{{ old('phone', $contact->phone) }}" placeholder="Phone" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            <input name="alternate_phone" value="{{ old('alternate_phone', $contact->alternate_phone) }}" placeholder="Alternate phone" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
        </div>
        <textarea name="notes" rows="4" placeholder="Notes" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('notes', $contact->notes) }}</textarea>
    </section>

    <aside class="space-y-5">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Association</h2>
            <div class="mt-5 space-y-4">
                <select name="crm_company_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">No company</option>
                    @foreach ($crmCompanies as $crmCompany)
                        <option value="{{ $crmCompany->id }}" @selected((int) old('crm_company_id', $contact->crm_company_id) === $crmCompany->id)>{{ $crmCompany->name }}</option>
                    @endforeach
                </select>
                <select name="assigned_user_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((int) old('assigned_user_id', $contact->assigned_user_id) === $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
                <select name="preferred_contact_method" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @foreach ($methods as $contactMethod)
                        <option value="{{ $contactMethod->value }}" @selected($methodValue === $contactMethod->value)>{{ $contactMethod->label() }}</option>
                    @endforeach
                </select>
                <select name="tag_ids[]" multiple class="block min-h-28 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->id }}" @selected(in_array($tag->id, $selectedTags, true))>{{ $tag->name }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $contact->is_primary ?? false)) class="rounded border-slate-300">
                    Primary contact
                </label>
            </div>
        </section>
    </aside>
</div>

<div class="mt-6 flex justify-end">
    <button type="submit" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Save contact</button>
</div>

@php
    $priorityValue = old('priority', $lead->priority instanceof \App\Enums\Crm\LeadPriority ? $lead->priority->value : ($lead->priority ?? 'medium'));
    $selectedTags = collect(old('tag_ids', $lead->exists ? $lead->tags->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
@endphp

@csrf
@if (($method ?? 'POST') !== 'POST')
    @method($method)
@endif

<div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
    <section class="space-y-5 rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div>
            <label for="title" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Lead Title</label>
            <input id="title" name="title" value="{{ old('title', $lead->title) }}" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            @error('title') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="business_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Company Name</label>
                <input id="business_name" name="business_name" value="{{ old('business_name', $lead->business_name) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
            <div>
                <label for="contact_name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Lead Name</label>
                <input id="contact_name" name="contact_name" value="{{ old('contact_name', $lead->contact_name) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $lead->email) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Phone</label>
                <input id="phone" name="phone" value="{{ old('phone', $lead->phone) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
            <div>
                <label for="business_type" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Business Type</label>
                <input id="business_type" name="business_type" value="{{ old('business_type', $lead->business_type ?? $lead->industry) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
            <div>
                <label for="industry" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Industry</label>
                <input id="industry" name="industry" value="{{ old('industry', $lead->industry) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
            <div>
                <label for="expected_value" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Estimated Budget</label>
                <input id="expected_value" type="number" step="0.01" name="expected_value" value="{{ old('expected_value', $lead->expected_value) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
            <div>
                <label for="expected_timeline" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Expected Timeline</label>
                <input id="expected_timeline" name="expected_timeline" value="{{ old('expected_timeline', $lead->expected_timeline) }}" placeholder="e.g. This quarter" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
            <div>
                <label for="city" class="block text-sm font-medium text-slate-700 dark:text-slate-300">City</label>
                <input id="city" name="city" value="{{ old('city', $lead->city) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
            <div>
                <label for="country" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Country</label>
                <input id="country" name="country" value="{{ old('country', $lead->country) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            </div>
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Requirement</label>
            <textarea id="description" name="description" rows="6" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('description', $lead->description) }}</textarea>
        </div>
    </section>

    <aside class="space-y-5">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Pipeline</h2>
            <div class="mt-5 space-y-4">
                <div>
                    <label for="status_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Status</label>
                    <select id="status_id" name="status_id" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @foreach ($statuses as $status)
                            <option value="{{ $status->id }}" @selected((int) old('status_id', $lead->status_id) === $status->id)>{{ $status->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="source_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Source</label>
                    <select id="source_id" name="source_id" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        <option value="">No source</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}" @selected((int) old('source_id', $lead->source_id) === $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="priority" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Priority</label>
                    <select id="priority" name="priority" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @foreach ($priorities as $priority)
                            <option value="{{ $priority->value }}" @selected($priorityValue === $priority->value)>{{ $priority->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="assigned_user_id" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Assigned User</label>
                    <select id="assigned_user_id" name="assigned_user_id" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) old('assigned_user_id', $lead->assigned_user_id) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <input type="hidden" name="currency" value="{{ old('currency', $lead->currency ?? 'INR') }}">
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Links</h2>
            <div class="mt-5 space-y-4">
                <select name="crm_company_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">No CRM company</option>
                    @foreach ($crmCompanies as $crmCompany)
                        <option value="{{ $crmCompany->id }}" @selected((int) old('crm_company_id', $lead->crm_company_id) === $crmCompany->id)>{{ $crmCompany->name }}</option>
                    @endforeach
                </select>
                <select name="crm_contact_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    <option value="">No contact</option>
                    @foreach ($contacts as $contact)
                        <option value="{{ $contact->id }}" @selected((int) old('crm_contact_id', $lead->crm_contact_id) === $contact->id)>{{ $contact->fullName() }}</option>
                    @endforeach
                </select>
                <div>
                    <label for="next_follow_up_at" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Follow-up Date</label>
                    <input id="next_follow_up_at" type="datetime-local" name="next_follow_up_at" value="{{ old('next_follow_up_at', $lead->next_follow_up_at?->format('Y-m-d\TH:i')) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </div>
                <div>
                    <label for="last_contacted_at" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Last Contacted</label>
                    <input id="last_contacted_at" type="datetime-local" name="last_contacted_at" value="{{ old('last_contacted_at', $lead->last_contacted_at?->format('Y-m-d\TH:i')) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </div>
                <div>
                    <label for="lost_reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Lost Reason</label>
                    <input id="lost_reason" name="lost_reason" value="{{ old('lost_reason', $lead->lost_reason) }}" class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </div>
                <select name="tag_ids[]" multiple class="block min-h-28 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->id }}" @selected(in_array($tag->id, $selectedTags, true))>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>
        </section>
    </aside>
</div>

<div class="mt-6 flex justify-end">
    <button type="submit" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Save lead</button>
</div>

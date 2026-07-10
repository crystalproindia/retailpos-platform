@php
    $selectedTags = collect(old('tag_ids', $crmCompany->exists ? $crmCompany->tags->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
@endphp

@csrf
@if (($method ?? 'POST') !== 'POST')
    @method($method)
@endif

<div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]">
    <section class="space-y-5 rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-700 dark:text-slate-300">Company Name</label>
            <input id="name" name="name" value="{{ old('name', $crmCompany->name) }}" required class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            @error('name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-5 md:grid-cols-2">
            <input name="legal_name" value="{{ old('legal_name', $crmCompany->legal_name) }}" placeholder="Legal name" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            <input name="website" value="{{ old('website', $crmCompany->website) }}" placeholder="Website" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            <input name="industry" value="{{ old('industry', $crmCompany->industry) }}" placeholder="Industry" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            <input name="estimated_value" type="number" step="0.01" value="{{ old('estimated_value', $crmCompany->estimated_value) }}" placeholder="Estimated value" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            <input name="email" type="email" value="{{ old('email', $crmCompany->email) }}" placeholder="Email" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            <input name="phone" value="{{ old('phone', $crmCompany->phone) }}" placeholder="Phone" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            <input name="city" value="{{ old('city', $crmCompany->city) }}" placeholder="City" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
            <input name="state" value="{{ old('state', $crmCompany->state) }}" placeholder="State" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
        </div>
        <textarea name="address" rows="4" placeholder="Address" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">{{ old('address', $crmCompany->address) }}</textarea>
    </section>

    <aside class="space-y-5">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Ownership</h2>
            <div class="mt-5 space-y-4">
                <select name="assigned_user_id" class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((int) old('assigned_user_id', $crmCompany->assigned_user_id) === $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
                <select name="tag_ids[]" multiple class="block min-h-28 w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->id }}" @selected(in_array($tag->id, $selectedTags, true))>{{ $tag->name }}</option>
                    @endforeach
                </select>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $crmCompany->is_active ?? true)) class="rounded border-slate-300">
                    Active account
                </label>
            </div>
        </section>
    </aside>
</div>

<div class="mt-6 flex justify-end">
    <button type="submit" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-teal-300 dark:text-slate-950 dark:hover:bg-teal-200">Save company</button>
</div>

@csrf
@if ($supplier->exists)
    @method('PUT')
@endif

<div class="grid gap-4 md:grid-cols-2">
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Code
        <input name="code" value="{{ old('code', $supplier->code) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Name
        <input name="name" value="{{ old('name', $supplier->name) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Type
        <select name="supplier_type" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
            @foreach ($supplierTypes as $type)
                <option value="{{ $type->value }}" @selected(old('supplier_type', $supplier->supplier_type?->value ?? $supplier->supplier_type) === $type->value)>{{ str($type->value)->headline() }}</option>
            @endforeach
        </select>
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Email
        <input name="email" type="email" value="{{ old('email', $supplier->email) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Phone
        <input name="phone" value="{{ old('phone', $supplier->phone) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">GSTIN
        <input name="gstin" value="{{ old('gstin', $supplier->gstin) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">PAN
        <input name="pan" value="{{ old('pan', $supplier->pan) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Website
        <input name="website" type="url" value="{{ old('website', $supplier->website) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Payment terms
        <input name="payment_terms" value="{{ old('payment_terms', $supplier->payment_terms) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Credit limit
        <input name="credit_limit" type="number" step="0.01" min="0" value="{{ old('credit_limit', $supplier->credit_limit) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Currency
        <input name="default_currency" maxlength="3" value="{{ old('default_currency', $supplier->default_currency ?? 'INR') }}" class="mt-1 w-full rounded-md border-slate-300 uppercase dark:border-slate-700 dark:bg-slate-950" required>
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Lead time days
        <input name="lead_time_days" type="number" min="0" value="{{ old('lead_time_days', $supplier->lead_time_days) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Manual rating
        <input name="manual_rating" type="number" min="0" max="100" step="0.01" value="{{ old('manual_rating', $supplier->manual_rating) }}" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
    </label>
    <label class="flex items-center gap-2 pt-7 text-sm font-medium text-slate-700 dark:text-slate-200">
        <input name="is_active" type="checkbox" value="1" @checked(old('is_active', $supplier->is_active ?? true)) class="rounded border-slate-300">
        Active supplier
    </label>
</div>
<div class="mt-4 grid gap-4 md:grid-cols-2">
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Service notes
        <textarea name="service_notes" rows="3" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">{{ old('service_notes', $supplier->service_notes) }}</textarea>
    </label>
    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Internal notes
        <textarea name="notes" rows="3" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">{{ old('notes', $supplier->notes) }}</textarea>
    </label>
</div>
<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('purchases.suppliers.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium dark:border-slate-700">Cancel</a>
    <button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Save supplier</button>
</div>

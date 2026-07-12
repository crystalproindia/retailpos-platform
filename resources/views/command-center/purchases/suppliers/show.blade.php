@extends('layouts.admin')

@section('title', $supplier->name)
@section('page-title', $supplier->name)
@section('breadcrumbs')
    <span>/</span><span>Purchases</span><span>/</span><span>Suppliers</span><span>/</span><span>{{ $supplier->code }}</span>
@endsection

@section('content')
    @include('command-center.purchases.partials.nav')

    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ str($supplier->supplier_type->value)->headline() }} · {{ $supplier->email ?: 'No email' }}</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Rule-based supplier score. Product sales contribution is future-ready and not estimated from fake POS sales.</p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('purchases.suppliers.score', $supplier) }}">@csrf<button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-medium dark:border-slate-700">Recalculate score</button></form>
            <a href="{{ route('purchases.suppliers.edit', $supplier) }}" class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Edit</a>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr_1.2fr]">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Supplier profile</h2>
            <dl class="mt-4 grid gap-3 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Rating</dt><dd class="font-medium">{{ $supplier->rating ? number_format((float) $supplier->rating, 1) : 'No score' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Lead time</dt><dd class="font-medium">{{ $supplier->lead_time_days ?: 'Not set' }} days</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Payment terms</dt><dd class="font-medium">{{ $supplier->payment_terms ?: 'Not set' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Credit limit</dt><dd class="font-medium">₹{{ number_format((float) $supplier->credit_limit, 2) }}</dd></div>
            </dl>
            <div class="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $supplier->notes ?: 'No internal notes recorded.' }}</div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Mapped products</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr><th class="py-2">Product</th><th class="py-2 text-right">Price</th><th class="py-2">Lead time</th><th class="py-2">Preferred</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($supplier->products as $mapping)
                            <tr>
                                <td class="py-3 font-medium">{{ $mapping->product?->name }}</td>
                                <td class="py-3 text-right">₹{{ number_format((float) $mapping->purchase_price, 2) }}</td>
                                <td class="py-3">{{ $mapping->lead_time_days ?: 'Not set' }}</td>
                                <td class="py-3">{{ $mapping->is_preferred ? 'Yes' : 'No' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-500">No supplier products mapped.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-4">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold">Map product</h2>
            <form method="POST" action="{{ route('purchases.suppliers.products.store', $supplier) }}" class="mt-4 space-y-3">@csrf
                <select name="product_id" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                    <option value="">Select product</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}">{{ $product->name }}</option>
                    @endforeach
                </select>
                <input name="supplier_sku" placeholder="Supplier SKU" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                <div class="grid grid-cols-2 gap-3">
                    <input name="purchase_price" type="number" min="0" step="0.01" placeholder="Price" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                    <input name="minimum_order_quantity" type="number" min="0" step="0.001" placeholder="MOQ" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <input name="lead_time_days" type="number" min="0" placeholder="Lead days" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                    <select name="tax_rate_id" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                        <option value="">No tax</option>
                        @foreach ($taxRates as $taxRate)
                            <option value="{{ $taxRate->id }}">{{ $taxRate->name }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm"><input name="is_preferred" type="checkbox" value="1" class="rounded"> Preferred supplier</label>
                <label class="flex items-center gap-2 text-sm"><input name="is_active" type="checkbox" value="1" checked class="rounded"> Active</label>
                <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Save product</button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold">Add contact</h2>
            <form method="POST" action="{{ route('purchases.suppliers.contacts.store', $supplier) }}" class="mt-4 space-y-3">@csrf
                <input name="name" placeholder="Name" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                <input name="email" type="email" placeholder="Email" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                <input name="phone" placeholder="Phone" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950">
                <label class="flex items-center gap-2 text-sm"><input name="is_primary" type="checkbox" value="1" class="rounded"> Primary</label>
                <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Save contact</button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold">Add address</h2>
            <form method="POST" action="{{ route('purchases.suppliers.addresses.store', $supplier) }}" class="mt-4 space-y-3">@csrf
                <input name="type" value="office" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                <input name="address_line_1" placeholder="Address line" class="w-full rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required>
                <div class="grid grid-cols-2 gap-3"><input name="city" placeholder="City" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required><input name="state" placeholder="State" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required></div>
                <div class="grid grid-cols-2 gap-3"><input name="country" value="India" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required><input name="postal_code" placeholder="Postal code" class="rounded-md border-slate-300 dark:border-slate-700 dark:bg-slate-950" required></div>
                <button class="rounded-md bg-slate-950 px-3 py-2 text-sm font-medium text-white dark:bg-teal-300 dark:text-slate-950">Save address</button>
            </form>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="text-base font-semibold">Contacts</h2>
            <div class="mt-4 space-y-3 text-sm">
                @forelse ($supplier->contacts as $contact)
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800">
                        <p class="font-medium">{{ $contact->name }} {{ $contact->is_primary ? '(Primary)' : '' }}</p>
                        <p class="text-slate-500">{{ $contact->email ?: $contact->phone }}</p>
                    </div>
                @empty
                    <p class="text-slate-500">No contacts recorded.</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection

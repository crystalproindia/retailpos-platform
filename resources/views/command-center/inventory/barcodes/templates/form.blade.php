@extends('layouts.admin')

@section('title', $template->exists ? 'Edit Barcode Template' : 'New Barcode Template')
@section('page-title', $template->exists ? 'Edit Barcode Template' : 'New Barcode Template')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><a href="{{ route('inventory.barcode-templates.index') }}" class="hover:text-slate-950 dark:hover:text-white">Barcode Templates</a>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <form method="POST" action="{{ $template->exists ? route('inventory.barcode-templates.update', $template) : route('inventory.barcode-templates.store') }}" class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
        @csrf
        @if ($template->exists) @method('PUT') @endif
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="space-y-1 md:col-span-2"><span class="text-sm font-medium">Name</span><input name="name" value="{{ old('name', $template->name) }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="space-y-1"><span class="text-sm font-medium">Industry</span><input name="industry_type" value="{{ old('industry_type', $template->industry_type) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
                <label class="space-y-1"><span class="text-sm font-medium">Paper size</span><input name="paper_size" value="{{ old('paper_size', $template->paper_size) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
                @foreach (['label_width_mm' => 'Label width mm', 'label_height_mm' => 'Label height mm', 'columns' => 'Columns', 'rows' => 'Rows', 'gap_horizontal_mm' => 'Horizontal gap mm', 'gap_vertical_mm' => 'Vertical gap mm', 'margin_top_mm' => 'Top margin mm', 'margin_right_mm' => 'Right margin mm', 'margin_bottom_mm' => 'Bottom margin mm', 'margin_left_mm' => 'Left margin mm', 'font_size' => 'Font size'] as $field => $label)
                    <label class="space-y-1"><span class="text-sm font-medium">{{ $label }}</span><input name="{{ $field }}" type="number" step="0.01" value="{{ old($field, $template->{$field}) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
                @endforeach
                <label class="space-y-1"><span class="text-sm font-medium">Barcode type</span><input name="barcode_type" value="{{ old('barcode_type', $template->barcode_type ?? 'CODE128') }}" required class="w-full rounded-lg border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-950"></label>
            </div>
        </section>
        <aside class="space-y-4">
            <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Visible fields</h2>
                <div class="mt-4 grid gap-3 text-sm">
                    @foreach (['show_product_name' => 'Product name', 'show_sku' => 'SKU', 'show_barcode_text' => 'Barcode text', 'show_price' => 'Price', 'show_mrp' => 'MRP', 'show_offer_price' => 'Offer price', 'show_brand' => 'Brand', 'show_category' => 'Category', 'show_size' => 'Size', 'show_color' => 'Color', 'show_batch' => 'Batch', 'show_expiry' => 'Expiry', 'show_company_name' => 'Company name', 'show_logo' => 'Logo', 'is_default' => 'Default', 'is_active' => 'Active'] as $field => $label)
                        <label class="flex items-center gap-3"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $template->{$field})) class="rounded border-slate-300 text-teal-600"><span>{{ $label }}</span></label>
                    @endforeach
                </div>
            </section>
            @if ($errors->any())<div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
            <button class="w-full rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">Save template</button>
        </aside>
    </form>
@endsection

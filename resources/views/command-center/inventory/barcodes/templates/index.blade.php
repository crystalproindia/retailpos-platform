@extends('layouts.admin')

@section('title', 'Barcode Templates')
@section('page-title', 'Barcode Templates')
@section('breadcrumbs')
    <span>/</span><a href="{{ route('inventory.dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Inventory</a><span>/</span><span>Barcode Templates</span>
@endsection

@section('content')
    @include('command-center.inventory.partials.nav')
    <div class="mb-4 flex justify-end"><a href="{{ route('inventory.barcode-templates.create') }}" class="rounded-lg bg-teal-600 px-4 py-2 text-sm font-semibold text-white">New template</a></div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($templates as $template)
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-3">
                    <div><h2 class="font-semibold">{{ $template->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $template->label_width_mm }}mm x {{ $template->label_height_mm }}mm / {{ $template->columns }} columns</p></div>
                    @if($template->is_default)<span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">Default</span>@endif
                </div>
                <div class="mt-4 rounded-lg border border-dashed border-slate-300 p-3 dark:border-slate-700" style="max-width: {{ min(220, (float) $template->label_width_mm * 4) }}px">
                    @if($template->show_company_name)<p class="text-center text-[10px] font-semibold">Crystal Retail Demo</p>@endif
                    @if($template->show_product_name)<p class="truncate text-center text-xs font-semibold">Demo Product</p>@endif
                    <div class="mx-auto mt-2 h-9 w-32 bg-[repeating-linear-gradient(90deg,#0f172a_0_2px,transparent_2px_4px,#0f172a_4px_5px,transparent_5px_8px)]"></div>
                    @if($template->show_barcode_text)<p class="mt-1 text-center font-mono text-[10px]">8901234567890</p>@endif
                    @if($template->show_price)<p class="mt-1 text-center text-xs font-bold">₹999.00</p>@endif
                </div>
                <div class="mt-4 flex items-center justify-between gap-3">
                    <a href="{{ route('inventory.barcode-templates.edit', $template) }}" class="text-sm font-semibold text-slate-700 hover:text-teal-700 dark:text-slate-300">Edit</a>
                    @unless($template->is_default)
                        <form method="POST" action="{{ route('inventory.barcode-templates.default', $template) }}">@csrf<button class="text-sm font-semibold text-teal-700">Set default</button></form>
                    @endunless
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-slate-200 bg-white p-8 text-center text-slate-500 dark:border-slate-800 dark:bg-slate-900">No label templates yet.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $templates->links() }}</div>
@endsection

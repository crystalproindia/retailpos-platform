<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BarcodeTemplateRequest;
use App\Models\Inventory\BarcodeLabelTemplate;
use App\Services\Inventory\BarcodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarcodeLabelTemplateController extends Controller
{
    public function index(Request $request): View
    {
        return view('command-center.inventory.barcodes.templates.index', [
            'templates' => BarcodeLabelTemplate::query()
                ->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $request->user()->company_id))
                ->withTrashed()
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('command-center.inventory.barcodes.templates.form', [
            'template' => new BarcodeLabelTemplate([
                'label_width_mm' => 50,
                'label_height_mm' => 25,
                'columns' => 2,
                'barcode_type' => 'CODE128',
                'font_size' => 10,
                'show_product_name' => true,
                'show_sku' => true,
                'show_barcode_text' => true,
                'show_price' => true,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(BarcodeTemplateRequest $request, BarcodeService $barcodes): RedirectResponse
    {
        $barcodes->saveTemplate($request->user(), $request->validated());

        return redirect()->route('inventory.barcode-templates.index')->with('status', 'Barcode template created.');
    }

    public function edit(Request $request, int $template): View
    {
        return view('command-center.inventory.barcodes.templates.form', [
            'template' => BarcodeLabelTemplate::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $request->user()->company_id))->withTrashed()->findOrFail($template),
        ]);
    }

    public function update(BarcodeTemplateRequest $request, BarcodeService $barcodes, int $template): RedirectResponse
    {
        $model = BarcodeLabelTemplate::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $request->user()->company_id))->findOrFail($template);
        $barcodes->saveTemplate($request->user(), $request->validated(), $model);

        return back()->with('status', 'Barcode template updated.');
    }

    public function setDefault(Request $request, BarcodeService $barcodes, int $template): RedirectResponse
    {
        $model = BarcodeLabelTemplate::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $request->user()->company_id))->findOrFail($template);
        $barcodes->setDefault($request->user(), $model);

        return back()->with('status', 'Default barcode template updated.');
    }
}

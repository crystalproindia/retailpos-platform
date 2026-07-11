<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\BarcodeLabelTemplate;
use App\Models\Inventory\BarcodePrintBatch;
use App\Repositories\Inventory\ProductRepository;
use App\Services\Inventory\BarcodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BarcodePrintBatchController extends Controller
{
    public function index(Request $request): View
    {
        return view('command-center.inventory.barcodes.print-batches.index', [
            'batches' => BarcodePrintBatch::query()->with(['template', 'creator'])->where('company_id', $request->user()->company_id)->latest()->paginate(15),
        ]);
    }

    public function create(Request $request, ProductRepository $products): View
    {
        return view('command-center.inventory.barcodes.print-batches.create', [
            'templates' => BarcodeLabelTemplate::query()->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', $request->user()->company_id))->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get(),
            'products' => $products->activeForCompany($request->user()->company_id),
        ]);
    }

    public function store(Request $request, BarcodeService $barcodes): RedirectResponse
    {
        $validated = $request->validate([
            'template_id' => ['required', 'integer', 'exists:barcode_label_templates,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $batch = $barcodes->createPrintBatch($request->user(), $validated);

        return redirect()->route('inventory.barcode-batches.show', $batch)->with('status', 'Barcode print batch created.');
    }

    public function show(Request $request, int $batch): View
    {
        return view('command-center.inventory.barcodes.print-batches.show', [
            'batch' => BarcodePrintBatch::query()->with(['template', 'items.product'])->where('company_id', $request->user()->company_id)->findOrFail($batch),
        ]);
    }
}

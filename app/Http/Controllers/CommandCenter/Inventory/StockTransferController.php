<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockTransfer;
use App\Services\Inventory\StockTransferService;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function index(Request $request, OutletAccessService $outlets): View
    {
        return view('command-center.inventory.transfers.index', ['transfers' => StockTransfer::query()->where('company_id', $request->user()->company_id)->with(['sourceOutlet', 'destinationOutlet', 'items.product'])->latest()->paginate(20), 'outlets' => $outlets->accessibleOutlets($request->user()), 'products' => Product::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get()]);
    }

    public function store(Request $request, StockTransferService $transfers): RedirectResponse
    {
        $data = $request->validate(['source_branch_id' => ['required', 'integer'], 'destination_branch_id' => ['required', 'integer', 'different:source_branch_id'], 'notes' => ['nullable', 'string', 'max:3000'], 'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $request->user()->company_id)], 'items.*.quantity' => ['required', 'numeric', 'gt:0']]);
        $transfer = $transfers->create($request->user(), $data);
        return redirect()->route('inventory.transfers.index')->with('status', 'Transfer '.$transfer->transfer_number.' saved as a draft.');
    }

    public function dispatch(Request $request, StockTransferService $transfers, int $transfer): RedirectResponse
    {
        $record = StockTransfer::query()->where('company_id', $request->user()->company_id)->findOrFail($transfer);
        $transfers->dispatch($record, $request->user());
        return back()->with('status', 'Transfer dispatched. Source outlet stock is now updated.');
    }

    public function receive(Request $request, StockTransferService $transfers, int $transfer): RedirectResponse
    {
        $record = StockTransfer::query()->where('company_id', $request->user()->company_id)->findOrFail($transfer);
        $transfers->receive($record, $request->user());
        return back()->with('status', 'Transfer received. Destination outlet stock is now updated.');
    }
}

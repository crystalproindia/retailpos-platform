<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryBatch;
use App\Models\Inventory\InventorySerialNumber;
use App\Models\Inventory\InventoryTransferDiscrepancy;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockLocation;
use App\Models\Inventory\StockTransfer;
use App\Models\User;
use App\Services\Inventory\InventoryLocationAccessService;
use App\Services\Inventory\StockTransferService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function index(Request $request, InventoryLocationAccessService $access): View
    {
        $warehouses = $access->accessibleWarehouses($request->user());
        $warehouseIds = $warehouses->pluck('id');
        $base = StockTransfer::query()
            ->where('company_id', $request->user()->company_id)
            ->where(fn (Builder $query) => $query->whereIn('source_warehouse_id', $warehouseIds)->orWhereIn('destination_warehouse_id', $warehouseIds));
        $transfers = (clone $base)
            ->with(['sourceWarehouse.branch', 'destinationWarehouse.branch', 'requester', 'items.product'])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->input('q'))).'%';
                $query->where('transfer_number', 'like', $term);
            })
            ->when($request->filled('source'), fn (Builder $query) => $query->where('source_warehouse_id', $request->integer('source')))
            ->when($request->filled('destination'), fn (Builder $query) => $query->where('destination_warehouse_id', $request->integer('destination')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('status', $request->string('status')))
            ->when($request->filled('product'), fn (Builder $query) => $query->whereHas('items', fn (Builder $items) => $items->where('product_id', $request->integer('product'))))
            ->when($request->filled('requested_by'), fn (Builder $query) => $query->where('requested_by', $request->integer('requested_by')))
            ->when($request->filled('date_from'), fn (Builder $query) => $query->whereDate('created_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn (Builder $query) => $query->whereDate('created_at', '<=', $request->date('date_to')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('command-center.inventory.transfers.index', [
            'transfers' => $transfers,
            'warehouses' => $warehouses,
            'products' => Product::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->limit(250)->get(['id', 'name']),
            'users' => User::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'metrics' => [
                'draft' => (clone $base)->where('status', 'draft')->count(),
                'awaiting' => (clone $base)->whereIn('status', ['requested', 'pending_approval'])->count(),
                'approved' => (clone $base)->where('status', 'approved')->count(),
                'packing' => (clone $base)->where('status', 'packing')->count(),
                'ready' => (clone $base)->whereIn('status', ['approved', 'packing'])->whereHas('items', fn (Builder $items) => $items->where('packed_quantity', '>', 0))->count(),
                'transit' => (clone $base)->whereIn('status', ['dispatched', 'in_transit'])->count(),
                'partial' => (clone $base)->where('status', 'partially_received')->count(),
                'discrepancy' => (clone $base)->where('status', 'discrepancy')->count(),
                'overdue' => (clone $base)->whereIn('status', ['in_transit', 'partially_received', 'discrepancy'])->where('expected_arrival_at', '<', now())->count(),
                'completed_today' => (clone $base)->where('status', 'received')->whereDate('received_at', today())->count(),
            ],
        ]);
    }

    public function create(Request $request, InventoryLocationAccessService $access): View
    {
        $warehouses = $access->accessibleWarehouses($request->user());
        $warehouseIds = $warehouses->pluck('id');

        return view('command-center.inventory.transfers.create', [
            'warehouses' => $warehouses,
            'locations' => StockLocation::query()->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $warehouseIds)->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function products(Request $request, InventoryLocationAccessService $access): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:120'],
            'source_warehouse_id' => ['required', 'integer'],
            'destination_warehouse_id' => ['required', 'integer', 'different:source_warehouse_id'],
            'source_stock_location_id' => ['nullable', 'integer'],
            'destination_stock_location_id' => ['nullable', 'integer'],
        ]);
        $warehouses = $access->accessibleWarehouses($request->user())->keyBy('id');
        $source = $warehouses->get((int) $data['source_warehouse_id']);
        $destination = $warehouses->get((int) $data['destination_warehouse_id']);
        abort_unless($source && $destination, 403);
        $sourceLocationId = $this->locationId($request, (int) $source->id, $data['source_stock_location_id'] ?? null);
        $destinationLocationId = $this->locationId($request, (int) $destination->id, $data['destination_stock_location_id'] ?? null);
        $term = trim($data['q']);
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
        $products = Product::query()
            ->with('unit')
            ->where('company_id', $request->user()->company_id)
            ->where('is_active', true)
            ->where(fn (Builder $query) => $query->where('barcode', $term)->orWhere('sku', $term)->orWhere('name', 'like', $like)->orWhere('sku', 'like', $like))
            ->orderByRaw('CASE WHEN barcode = ? OR sku = ? THEN 0 ELSE 1 END', [$term, $term])
            ->orderBy('name')
            ->limit(20)
            ->get();
        $productIds = $products->pluck('id');
        $stock = StockLevel::query()
            ->where('company_id', $request->user()->company_id)
            ->whereIn('product_id', $productIds)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $sourceStock) => $sourceStock
                    ->where('warehouse_id', $source->id)
                    ->when($sourceLocationId, fn (Builder $location) => $location->where('stock_location_id', $sourceLocationId), fn (Builder $location) => $location->whereNull('stock_location_id')))
                ->orWhere(fn (Builder $destinationStock) => $destinationStock
                    ->where('warehouse_id', $destination->id)
                    ->when($destinationLocationId, fn (Builder $location) => $location->where('stock_location_id', $destinationLocationId), fn (Builder $location) => $location->whereNull('stock_location_id'))))
            ->get()
            ->groupBy(fn (StockLevel $level) => $level->warehouse_id.'-'.$level->product_id)
            ->map->sum('quantity_available');

        return response()->json([
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'image' => $product->imageUrl(true),
                'unit' => $product->unit?->short_code,
                'track_serials' => $product->track_serials,
                'track_batches' => $product->track_batches,
            ]),
            'stock' => $stock,
            'serials' => InventorySerialNumber::query()->where('company_id', $request->user()->company_id)->where('warehouse_id', $source->id)->when($sourceLocationId, fn (Builder $query) => $query->where('stock_location_id', $sourceLocationId), fn (Builder $query) => $query->whereNull('stock_location_id'))->whereIn('product_id', $productIds)->where('status', 'available')->orderBy('serial_number')->limit(500)->get(['id', 'product_id', 'warehouse_id', 'serial_number']),
            'batches' => InventoryBatch::query()->where('company_id', $request->user()->company_id)->where('warehouse_id', $source->id)->when($sourceLocationId, fn (Builder $query) => $query->where('stock_location_id', $sourceLocationId), fn (Builder $query) => $query->whereNull('stock_location_id'))->whereIn('product_id', $productIds)->where('status', 'active')->where('quantity_available', '>', 0)->orderBy('expires_at')->limit(200)->get(['id', 'product_id', 'warehouse_id', 'batch_number', 'expires_at', 'quantity_available']),
        ]);
    }

    public function store(Request $request, StockTransferService $transfers): RedirectResponse
    {
        $data = $request->validate([
            'source_warehouse_id' => ['required', 'integer'],
            'destination_warehouse_id' => ['required', 'integer', 'different:source_warehouse_id'],
            'source_stock_location_id' => ['nullable', 'integer'],
            'destination_stock_location_id' => ['nullable', 'integer'],
            'expected_arrival_at' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('company_id', $request->user()->company_id)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.source_stock_location_id' => ['nullable', 'integer'],
            'items.*.destination_stock_location_id' => ['nullable', 'integer'],
            'items.*.inventory_batch_id' => ['nullable', 'integer'],
            'items.*.serial_ids' => ['nullable', 'array'],
            'items.*.serial_ids.*' => ['integer', 'distinct'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $transfer = $transfers->create($request->user(), $data);

        return redirect()->route('inventory.transfers.show', $transfer)->with('status', 'Transfer '.$transfer->transfer_number.' saved as a draft.');
    }

    public function show(Request $request, InventoryLocationAccessService $access, int $transfer): View
    {
        $record = $this->record($request, $access, $transfer);

        return view('command-center.inventory.transfers.show', ['transfer' => $record]);
    }

    public function submit(Request $request, StockTransferService $service, InventoryLocationAccessService $access, int $transfer): RedirectResponse
    {
        $service->submit($this->record($request, $access, $transfer), $request->user());

        return back()->with('status', 'Transfer submitted.');
    }

    public function approve(Request $request, StockTransferService $service, InventoryLocationAccessService $access, int $transfer): RedirectResponse
    {
        $data = $request->validate(['items' => ['nullable', 'array'], 'items.*.id' => ['required', 'integer'], 'items.*.approved_quantity' => ['required', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:3000']]);
        $service->approve($this->record($request, $access, $transfer), $request->user(), $data['items'] ?? [], $data['notes'] ?? null);

        return back()->with('status', 'Transfer approved.');
    }

    public function reject(Request $request, StockTransferService $service, InventoryLocationAccessService $access, int $transfer): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:3000']]);
        $service->reject($this->record($request, $access, $transfer), $request->user(), $data['reason']);

        return back()->with('status', 'Transfer rejected.');
    }

    public function pack(Request $request, StockTransferService $service, InventoryLocationAccessService $access, int $transfer): RedirectResponse
    {
        $data = $request->validate(['items' => ['required', 'array', 'min:1'], 'items.*.id' => ['required', 'integer'], 'items.*.packed_quantity' => ['required', 'numeric', 'min:0']]);
        $service->pack($this->record($request, $access, $transfer), $request->user(), $data['items']);

        return back()->with('status', 'Packing quantities saved.');
    }

    public function dispatch(Request $request, StockTransferService $service, InventoryLocationAccessService $access, int $transfer): RedirectResponse
    {
        $service->dispatch($this->record($request, $access, $transfer), $request->user());

        return back()->with('status', 'Transfer dispatched. Stock is now in transit and is not available at the destination.');
    }

    public function receive(Request $request, StockTransferService $service, InventoryLocationAccessService $access, int $transfer): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.received_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.damaged_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.short_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
            'items.*.serial_ids' => ['nullable', 'array'],
            'items.*.serial_ids.*' => ['integer', 'distinct'],
        ]);
        $service->receive($this->record($request, $access, $transfer), $request->user(), $data);

        return back()->with('status', 'Receipt recorded. Remaining quantities stay in transit until resolved.');
    }

    public function cancel(Request $request, StockTransferService $service, InventoryLocationAccessService $access, int $transfer): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:3000']]);
        $service->cancel($this->record($request, $access, $transfer), $request->user(), $data['reason']);

        return back()->with('status', 'Transfer cancelled.');
    }

    public function reportDiscrepancy(Request $request, StockTransferService $service, InventoryLocationAccessService $access, int $transfer): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(['short_received', 'damaged_in_transit', 'wrong_item', 'excess_received', 'missing_package', 'other'])],
            'reason' => ['required', 'string', 'max:255'],
            'expected_quantity' => ['nullable', 'numeric', 'min:0'],
            'actual_quantity' => ['nullable', 'numeric', 'min:0'],
            'discrepancy_quantity' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $record = $this->record($request, $access, $transfer);
        $item = $record->items->firstWhere('id', (int) $data['item_id']);
        abort_unless($item, 404);
        $service->reportDiscrepancy($record, $item, $request->user(), $data);

        return back()->with('status', 'Discrepancy recorded without changing stock.');
    }

    public function resolve(Request $request, StockTransferService $service, int $discrepancy): RedirectResponse
    {
        $data = $request->validate(['resolution' => ['required', Rule::in(['confirm_loss', 'restock_source', 'add_destination_damaged', 'manager_adjustment'])], 'notes' => ['nullable', 'string', 'max:3000']]);
        $record = InventoryTransferDiscrepancy::query()->where('company_id', $request->user()->company_id)->findOrFail($discrepancy);
        $service->resolveDiscrepancy($record, $request->user(), $data['resolution'], $data['notes'] ?? null);

        return back()->with('status', 'Discrepancy resolved and recorded in the audit trail.');
    }

    public function printDocument(Request $request, InventoryLocationAccessService $access, int $transfer): View
    {
        return view('command-center.inventory.transfers.print', ['transfer' => $this->record($request, $access, $transfer)]);
    }

    private function record(Request $request, InventoryLocationAccessService $access, int $id): StockTransfer
    {
        $warehouseIds = $access->accessibleWarehouses($request->user())->pluck('id');

        return StockTransfer::query()
            ->where('company_id', $request->user()->company_id)
            ->where(fn (Builder $query) => $query->whereIn('source_warehouse_id', $warehouseIds)->orWhereIn('destination_warehouse_id', $warehouseIds))
            ->with(['sourceWarehouse.branch', 'destinationWarehouse.branch', 'sourceLocation', 'destinationLocation', 'requester', 'approver', 'packer', 'items.product.unit', 'receipts.items.transferItem.product', 'receipts.receiver', 'discrepancies.transferItem.product', 'discrepancies.reporter', 'discrepancies.resolver'])
            ->findOrFail($id);
    }

    private function locationId(Request $request, int $warehouseId, mixed $locationId): ?int
    {
        if (! $locationId) {
            return null;
        }

        return StockLocation::query()
            ->where('company_id', $request->user()->company_id)
            ->where('warehouse_id', $warehouseId)
            ->where('is_active', true)
            ->findOrFail((int) $locationId)
            ->id;
    }
}

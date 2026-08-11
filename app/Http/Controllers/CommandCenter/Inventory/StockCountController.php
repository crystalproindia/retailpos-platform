<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryStockCount;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockLocation;
use App\Models\User;
use App\Services\Inventory\InventoryLocationAccessService;
use App\Services\Inventory\StockCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockCountController extends Controller
{
    public function index(Request $request, InventoryLocationAccessService $access): View
    {
        $warehouses = $access->accessibleWarehouses($request->user());
        $counts = InventoryStockCount::query()->with(['warehouse', 'assignee', 'creator'])->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $warehouses->pluck('id'))->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))->latest()->paginate(20)->withQueryString();

        return view('command-center.inventory.counts.index', compact('counts'));
    }

    public function create(Request $request, InventoryLocationAccessService $access): View
    {
        $warehouses = $access->accessibleWarehouses($request->user());

        return view('command-center.inventory.counts.create', [
            'warehouses' => $warehouses,
            'locations' => StockLocation::query()->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $warehouses->pluck('id'))->where('is_active', true)->orderBy('code')->get(),
            'categories' => InventoryCategory::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->where('company_id', $request->user()->company_id)->where('track_inventory', true)->where('is_active', true)->orderBy('name')->get(),
            'users' => User::query()->where('company_id', $request->user()->company_id)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, StockCountService $service): RedirectResponse
    {
        $data = $request->validate(['warehouse_id' => ['required', 'integer'], 'stock_location_id' => ['nullable', 'integer'], 'type' => ['required', 'in:full,warehouse,category,selected,cycle'], 'category_id' => ['nullable', 'integer'], 'product_ids' => ['nullable', 'array', 'max:500'], 'product_ids.*' => ['integer'], 'assigned_to' => ['nullable', 'integer'], 'due_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:3000']]);
        $count = $service->create($request->user(), $data);

        return redirect()->route('inventory.counts.show', $count)->with('status', 'Stock count created. Balances are not changed until approval and posting.');
    }

    public function show(Request $request, InventoryLocationAccessService $access, int $count): View
    {
        return view('command-center.inventory.counts.show', ['count' => $this->findCount($request, $access, $count)]);
    }

    public function save(Request $request, StockCountService $service, InventoryLocationAccessService $access, int $count): RedirectResponse
    {
        $data = $request->validate(['items' => ['required', 'array'], 'items.*.id' => ['required', 'integer'], 'items.*.counted_quantity' => ['nullable', 'numeric'], 'items.*.notes' => ['nullable', 'string', 'max:1000']]);
        $service->record($this->findCount($request, $access, $count), $request->user(), $data['items']);

        return back()->with('status', 'Counted quantities saved.');
    }

    public function submit(Request $request, StockCountService $service, InventoryLocationAccessService $access, int $count): RedirectResponse
    {
        $service->submit($this->findCount($request, $access, $count), $request->user());

        return back()->with('status', 'Count submitted for review.');
    }

    public function approve(Request $request, StockCountService $service, InventoryLocationAccessService $access, int $count): RedirectResponse
    {
        $service->approve($this->findCount($request, $access, $count), $request->user());

        return back()->with('status', 'Count approved. Stock has not changed yet.');
    }

    public function post(Request $request, StockCountService $service, InventoryLocationAccessService $access, int $count): RedirectResponse
    {
        $service->post($this->findCount($request, $access, $count), $request->user());

        return back()->with('status', 'Count variances posted to the stock ledger.');
    }

    private function findCount(Request $request, InventoryLocationAccessService $access, int $id): InventoryStockCount
    {
        return InventoryStockCount::query()->with(['warehouse', 'location', 'assignee', 'creator', 'items.product'])->where('company_id', $request->user()->company_id)->whereIn('warehouse_id', $access->accessibleWarehouses($request->user())->pluck('id'))->findOrFail($id);
    }
}

<?php

namespace App\Http\Controllers\CommandCenter\Purchases;

use App\Enums\Purchases\PurchaseRequestPriority;
use App\Enums\Purchases\PurchaseSourceType;
use App\Http\Controllers\Controller;
use App\Repositories\Inventory\InventoryLookupRepository;
use App\Repositories\Inventory\ProductRepository;
use App\Repositories\Purchases\PurchaseRequestRepository;
use App\Repositories\Purchases\SupplierRepository;
use App\Services\Inventory\InventoryLocationAccessService;
use App\Services\Purchases\PurchaseOrderService;
use App\Services\Purchases\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function index(Request $request, PurchaseRequestRepository $requests): View
    {
        return view('command-center.purchases.requests.index', [
            'requests' => $requests->paginateForUser($request->user(), $request->only(['search', 'status', 'priority', 'source_type'])),
            'priorities' => PurchaseRequestPriority::cases(),
            'sources' => PurchaseSourceType::cases(),
        ]);
    }

    public function create(Request $request, ProductRepository $products, SupplierRepository $suppliers, InventoryLookupRepository $lookups, InventoryLocationAccessService $access): View
    {
        return view('command-center.purchases.requests.create', [
            'products' => $products->activeForCompany($request->user()->company_id),
            'suppliers' => $suppliers->activeForCompany($request->user()->company_id),
            'warehouses' => $access->accessibleWarehouses($request->user()),
            'priorities' => PurchaseRequestPriority::cases(),
        ]);
    }

    public function store(Request $request, PurchaseRequestService $service): RedirectResponse
    {
        $purchaseRequest = $service->create($request->user(), $this->validatedRequest($request));

        return redirect()->route('purchases.requests.show', $purchaseRequest)->with('status', 'Purchase request created.');
    }

    public function show(Request $request, PurchaseRequestRepository $requests, SupplierRepository $suppliers, int $purchaseRequest): View
    {
        return view('command-center.purchases.requests.show', [
            'purchaseRequest' => $requests->findForUser($request->user(), $purchaseRequest),
            'suppliers' => $suppliers->activeForCompany($request->user()->company_id),
        ]);
    }

    public function submit(Request $request, PurchaseRequestService $service, PurchaseRequestRepository $requests, int $purchaseRequest): RedirectResponse
    {
        $service->submit($requests->findForUser($request->user(), $purchaseRequest), $request->user());

        return back()->with('status', 'Purchase request submitted.');
    }

    public function approve(Request $request, PurchaseRequestService $service, PurchaseRequestRepository $requests, int $purchaseRequest): RedirectResponse
    {
        $validated = $request->validate([
            'comments' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array'],
            'items.*.item_id' => ['required_with:items', 'integer'],
            'items.*.approved_quantity' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.approval_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $service->approve($requests->findForUser($request->user(), $purchaseRequest), $request->user(), $validated['items'] ?? [], $validated['comments'] ?? null);

        return back()->with('status', 'Purchase request approved.');
    }

    public function reject(Request $request, PurchaseRequestService $service, PurchaseRequestRepository $requests, int $purchaseRequest): RedirectResponse
    {
        $service->reject($requests->findForUser($request->user(), $purchaseRequest), $request->user(), $request->input('comments'));

        return back()->with('status', 'Purchase request rejected.');
    }

    public function convert(Request $request, PurchaseOrderService $orders, PurchaseRequestRepository $requests, int $purchaseRequest): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $request->user()->company_id)],
        ]);
        $order = $orders->createFromRequest($requests->findForUser($request->user(), $purchaseRequest), $request->user(), $validated['supplier_id'] ?? null);

        return redirect()->route('purchases.orders.show', $order)->with('status', 'Purchase request converted to purchase order.');
    }

    public function createFromReorder(Request $request, PurchaseRequestService $service): RedirectResponse
    {
        $validated = $request->validate([
            'suggestion_ids' => ['required', 'array', 'min:1'],
            'suggestion_ids.*' => ['integer', Rule::exists('reorder_suggestions', 'id')->where('company_id', $request->user()->company_id)],
        ]);

        $purchaseRequest = $service->createFromReorderSuggestions($request->user(), $validated['suggestion_ids']);

        return redirect()->route('purchases.requests.show', $purchaseRequest)->with('status', 'Purchase request created from reorder suggestions.');
    }

    public function duplicate(Request $request, PurchaseRequestService $service, PurchaseRequestRepository $requests, int $purchaseRequest): RedirectResponse
    {
        $copy = $service->duplicate($requests->findForUser($request->user(), $purchaseRequest), $request->user());

        return redirect()->route('purchases.requests.show', $copy)->with('status', 'Purchase request duplicated as a draft.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedRequest(Request $request): array
    {
        return $request->validate([
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $request->user()->company_id)],
            'priority' => ['required', Rule::in(collect(PurchaseRequestPriority::cases())->pluck('value')->all())],
            'expected_by' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $request->user()->company_id)],
            'items.*.supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $request->user()->company_id)],
            'items.*.requested_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.estimated_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.expected_by' => ['nullable', 'date'],
            'items.*.notes' => ['nullable', 'string'],
        ]);
    }
}

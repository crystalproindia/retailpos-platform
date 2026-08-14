<?php

namespace App\Http\Controllers\CommandCenter\Purchases;

use App\Http\Controllers\Controller;
use App\Repositories\Inventory\InventoryLookupRepository;
use App\Repositories\Inventory\ProductRepository;
use App\Repositories\Purchases\PurchaseOrderRepository;
use App\Repositories\Purchases\SupplierRepository;
use App\Services\Inventory\InventoryLocationAccessService;
use App\Services\Purchases\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request, PurchaseOrderRepository $orders, SupplierRepository $suppliers, InventoryLookupRepository $lookups, InventoryLocationAccessService $access): View
    {
        return view('command-center.purchases.orders.index', [
            'orders' => $orders->paginateForUser($request->user(), $request->only(['search', 'status', 'supplier_id', 'warehouse_id'])),
            'suppliers' => $suppliers->activeForCompany($request->user()->company_id),
            'warehouses' => $access->accessibleWarehouses($request->user()),
        ]);
    }

    public function create(Request $request, ProductRepository $products, SupplierRepository $suppliers, InventoryLookupRepository $lookups, InventoryLocationAccessService $access): View
    {
        return view('command-center.purchases.orders.create', [
            'products' => $products->activeForCompany($request->user()->company_id),
            'suppliers' => $suppliers->activeForCompany($request->user()->company_id),
            'warehouses' => $access->accessibleWarehouses($request->user()),
        ]);
    }

    public function store(Request $request, PurchaseOrderService $service): RedirectResponse
    {
        $order = $service->create($request->user(), $this->validatedOrder($request));

        return redirect()->route('purchases.orders.show', $order)->with('status', 'Purchase order created.');
    }

    public function show(Request $request, PurchaseOrderRepository $orders, int $purchaseOrder): View
    {
        return view('command-center.purchases.orders.show', [
            'order' => $orders->findForUser($request->user(), $purchaseOrder),
        ]);
    }

    public function submit(Request $request, PurchaseOrderService $service, PurchaseOrderRepository $orders, int $purchaseOrder): RedirectResponse
    {
        $service->submit($orders->findForUser($request->user(), $purchaseOrder), $request->user());

        return back()->with('status', 'Purchase order submitted.');
    }

    public function approve(Request $request, PurchaseOrderService $service, PurchaseOrderRepository $orders, int $purchaseOrder): RedirectResponse
    {
        $service->approve($orders->findForUser($request->user(), $purchaseOrder), $request->user());

        return back()->with('status', 'Purchase order approved.');
    }

    public function send(Request $request, PurchaseOrderService $service, PurchaseOrderRepository $orders, int $purchaseOrder): RedirectResponse
    {
        $service->markSent($orders->findForUser($request->user(), $purchaseOrder), $request->user());

        return back()->with('status', 'Purchase order marked sent.');
    }

    public function supplierConfirm(Request $request, PurchaseOrderService $service, PurchaseOrderRepository $orders, int $purchaseOrder): RedirectResponse
    {
        $validated = $request->validate(['reference' => ['nullable', 'string', 'max:120']]);
        $service->markSupplierConfirmed($orders->findForUser($request->user(), $purchaseOrder), $request->user(), $validated['reference'] ?? null);

        return back()->with('status', 'Supplier confirmation recorded.');
    }

    public function cancel(Request $request, PurchaseOrderService $service, PurchaseOrderRepository $orders, int $purchaseOrder): RedirectResponse
    {
        $service->cancel($orders->findForUser($request->user(), $purchaseOrder), $request->user());

        return back()->with('status', 'Purchase order cancelled.');
    }

    public function print(Request $request, PurchaseOrderRepository $orders, int $purchaseOrder): View
    {
        return view('command-center.purchases.orders.print', [
            'order' => $orders->findForUser($request->user(), $purchaseOrder),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedOrder(Request $request): array
    {
        return $request->validate([
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $request->user()->company_id)],
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $request->user()->company_id)],
            'purchase_request_id' => ['nullable', 'integer', Rule::exists('purchase_requests', 'id')->where('company_id', $request->user()->company_id)],
            'order_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'shipping_total' => ['nullable', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $request->user()->company_id)],
            'items.*.supplier_product_id' => ['nullable', 'integer'],
            'items.*.ordered_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);
    }
}

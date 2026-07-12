<?php

namespace App\Http\Controllers\CommandCenter\Purchases;

use App\Http\Controllers\Controller;
use App\Repositories\Inventory\InventoryLookupRepository;
use App\Repositories\Inventory\ProductRepository;
use App\Repositories\Purchases\GoodsReceiptRepository;
use App\Repositories\Purchases\PurchaseOrderRepository;
use App\Repositories\Purchases\SupplierRepository;
use App\Services\Purchases\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function index(Request $request, GoodsReceiptRepository $receipts): View
    {
        return view('command-center.purchases.grn.index', [
            'receipts' => $receipts->paginateForCompany($request->user()->company_id),
        ]);
    }

    public function create(Request $request, ProductRepository $products, SupplierRepository $suppliers, PurchaseOrderRepository $orders, InventoryLookupRepository $lookups): View
    {
        return view('command-center.purchases.grn.create', [
            'products' => $products->activeForCompany($request->user()->company_id),
            'suppliers' => $suppliers->activeForCompany($request->user()->company_id),
            'orders' => $orders->paginateForCompany($request->user()->company_id, ['status' => 'sent'], 100),
            'warehouses' => $lookups->formOptions($request->user()->company_id)['warehouses'],
            'locations' => $lookups->formOptions($request->user()->company_id)['locations'],
        ]);
    }

    public function store(Request $request, GoodsReceiptService $service): RedirectResponse
    {
        $receipt = $service->create($request->user(), $this->validatedReceipt($request));

        return redirect()->route('purchases.grn.show', $receipt)->with('status', 'Goods receipt created.');
    }

    public function show(Request $request, GoodsReceiptRepository $receipts, int $goodsReceipt): View
    {
        return view('command-center.purchases.grn.show', [
            'receipt' => $receipts->findForCompany($request->user()->company_id, $goodsReceipt),
        ]);
    }

    public function receive(Request $request, GoodsReceiptService $service, GoodsReceiptRepository $receipts, int $goodsReceipt): RedirectResponse
    {
        $service->receive($receipts->findForCompany($request->user()->company_id, $goodsReceipt), $request->user());

        return back()->with('status', 'Goods receipt posted to stock.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedReceipt(Request $request): array
    {
        return $request->validate([
            'warehouse_id' => ['required_without:purchase_order_id', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $request->user()->company_id)],
            'supplier_id' => ['required_without:purchase_order_id', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $request->user()->company_id)],
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')->where('company_id', $request->user()->company_id)],
            'receipt_date' => ['nullable', 'date'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:255'],
            'supplier_invoice_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer'],
            'items.*.product_id' => ['required_without:items.*.purchase_order_item_id', 'integer', Rule::exists('products', 'id')->where('company_id', $request->user()->company_id)],
            'items.*.stock_location_id' => ['nullable', 'integer'],
            'items.*.ordered_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.received_quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.accepted_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.rejected_quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_number' => ['nullable', 'string', 'max:255'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.manufacture_date' => ['nullable', 'date'],
            'items.*.notes' => ['nullable', 'string'],
        ]);
    }
}

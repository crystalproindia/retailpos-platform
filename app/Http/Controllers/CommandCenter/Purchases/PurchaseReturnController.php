<?php

namespace App\Http\Controllers\CommandCenter\Purchases;

use App\Http\Controllers\Controller;
use App\Repositories\Inventory\InventoryLookupRepository;
use App\Repositories\Inventory\ProductRepository;
use App\Repositories\Purchases\GoodsReceiptRepository;
use App\Repositories\Purchases\PurchaseReturnRepository;
use App\Repositories\Purchases\SupplierRepository;
use App\Services\Purchases\PurchaseReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function index(Request $request, PurchaseReturnRepository $returns): View
    {
        return view('command-center.purchases.returns.index', [
            'returns' => $returns->paginateForCompany($request->user()->company_id),
        ]);
    }

    public function create(Request $request, ProductRepository $products, SupplierRepository $suppliers, GoodsReceiptRepository $receipts, InventoryLookupRepository $lookups): View
    {
        return view('command-center.purchases.returns.create', [
            'products' => $products->activeForCompany($request->user()->company_id),
            'suppliers' => $suppliers->activeForCompany($request->user()->company_id),
            'receipts' => $receipts->paginateForCompany($request->user()->company_id),
            'warehouses' => $lookups->formOptions($request->user()->company_id)['warehouses'],
            'locations' => $lookups->formOptions($request->user()->company_id)['locations'],
        ]);
    }

    public function store(Request $request, PurchaseReturnService $service): RedirectResponse
    {
        $return = $service->create($request->user(), $this->validatedReturn($request));

        return redirect()->route('purchases.returns.show', $return)->with('status', 'Purchase return created.');
    }

    public function show(Request $request, PurchaseReturnRepository $returns, int $purchaseReturn): View
    {
        return view('command-center.purchases.returns.show', [
            'return' => $returns->findForCompany($request->user()->company_id, $purchaseReturn),
        ]);
    }

    public function approve(Request $request, PurchaseReturnService $service, PurchaseReturnRepository $returns, int $purchaseReturn): RedirectResponse
    {
        $service->approve($returns->findForCompany($request->user()->company_id, $purchaseReturn), $request->user());

        return back()->with('status', 'Purchase return approved.');
    }

    public function complete(Request $request, PurchaseReturnService $service, PurchaseReturnRepository $returns, int $purchaseReturn): RedirectResponse
    {
        $service->complete($returns->findForCompany($request->user()->company_id, $purchaseReturn), $request->user());

        return back()->with('status', 'Purchase return completed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedReturn(Request $request): array
    {
        return $request->validate([
            'warehouse_id' => ['required_without:goods_receipt_id', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $request->user()->company_id)],
            'supplier_id' => ['required_without:goods_receipt_id', 'integer', Rule::exists('suppliers', 'id')->where('company_id', $request->user()->company_id)],
            'goods_receipt_id' => ['nullable', 'integer', Rule::exists('goods_receipts', 'id')->where('company_id', $request->user()->company_id)],
            'return_date' => ['nullable', 'date'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $request->user()->company_id)],
            'items.*.stock_location_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string'],
        ]);
    }
}

<?php

namespace App\Http\Controllers\CommandCenter\Purchases;

use App\Enums\Purchases\SupplierType;
use App\Http\Controllers\Controller;
use App\Models\Purchases\Supplier;
use App\Repositories\Inventory\InventoryLookupRepository;
use App\Repositories\Inventory\ProductRepository;
use App\Repositories\Purchases\SupplierRepository;
use App\Services\Purchases\SupplierScoreService;
use App\Services\Purchases\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request, SupplierRepository $suppliers): View
    {
        return view('command-center.purchases.suppliers.index', [
            'suppliers' => $suppliers->paginateForCompany($request->user()->company_id, $request->only(['search', 'supplier_type', 'status', 'rating', 'has_products', 'trashed'])),
            'supplierTypes' => SupplierType::cases(),
        ]);
    }

    public function create(): View
    {
        return view('command-center.purchases.suppliers.create', [
            'supplier' => new Supplier(['supplier_type' => SupplierType::Distributor->value, 'default_currency' => 'INR', 'is_active' => true]),
            'supplierTypes' => SupplierType::cases(),
        ]);
    }

    public function store(Request $request, SupplierService $service): RedirectResponse
    {
        $supplier = $service->create($request->user(), $this->validatedSupplier($request));

        return redirect()->route('purchases.suppliers.show', $supplier)->with('status', 'Supplier created.');
    }

    public function show(Request $request, SupplierRepository $suppliers, ProductRepository $products, InventoryLookupRepository $lookups, int $supplier): View
    {
        return view('command-center.purchases.suppliers.show', [
            'supplier' => $suppliers->findForCompany($request->user()->company_id, $supplier, true),
            'products' => $products->activeForCompany($request->user()->company_id),
            'taxRates' => $lookups->formOptions($request->user()->company_id)['taxRates'],
        ]);
    }

    public function edit(Request $request, SupplierRepository $suppliers, int $supplier): View
    {
        return view('command-center.purchases.suppliers.edit', [
            'supplier' => $suppliers->findForCompany($request->user()->company_id, $supplier, true),
            'supplierTypes' => SupplierType::cases(),
        ]);
    }

    public function update(Request $request, SupplierService $service, SupplierRepository $suppliers, int $supplier): RedirectResponse
    {
        $model = $suppliers->findForCompany($request->user()->company_id, $supplier, true);
        $service->update($model, $request->user(), $this->validatedSupplier($request, $model->id));

        return redirect()->route('purchases.suppliers.show', $model)->with('status', 'Supplier updated.');
    }

    public function destroy(Request $request, SupplierService $service, SupplierRepository $suppliers, int $supplier): RedirectResponse
    {
        $service->delete($suppliers->findForCompany($request->user()->company_id, $supplier));

        return redirect()->route('purchases.suppliers.index')->with('status', 'Supplier moved to trash.');
    }

    public function restore(Request $request, SupplierService $service, SupplierRepository $suppliers, int $supplier): RedirectResponse
    {
        $service->restore($suppliers->findForCompany($request->user()->company_id, $supplier, true));

        return back()->with('status', 'Supplier restored.');
    }

    public function storeContact(Request $request, SupplierService $service, SupplierRepository $suppliers, int $supplier): RedirectResponse
    {
        $service->saveContact($request->user(), $suppliers->findForCompany($request->user()->company_id, $supplier), $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]));

        return back()->with('status', 'Supplier contact saved.');
    }

    public function storeAddress(Request $request, SupplierService $service, SupplierRepository $suppliers, int $supplier): RedirectResponse
    {
        $service->saveAddress($request->user(), $suppliers->findForCompany($request->user()->company_id, $supplier), $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'postal_code' => ['required', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
        ]));

        return back()->with('status', 'Supplier address saved.');
    }

    public function storeProduct(Request $request, SupplierService $service, SupplierRepository $suppliers): RedirectResponse
    {
        $supplier = $suppliers->findForCompany($request->user()->company_id, (int) $request->route('supplier'));
        $service->mapProduct($request->user(), $supplier, $request->validate([
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('company_id', $request->user()->company_id)],
            'supplier_sku' => ['nullable', 'string', 'max:255'],
            'supplier_product_name' => ['nullable', 'string', 'max:255'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'mrp' => ['nullable', 'numeric', 'min:0'],
            'minimum_order_quantity' => ['nullable', 'numeric', 'min:0'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'tax_rate_id' => ['nullable', 'integer'],
            'is_preferred' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]));

        return back()->with('status', 'Supplier product mapping saved.');
    }

    public function score(Request $request, SupplierRepository $suppliers, SupplierScoreService $scores, int $supplier): RedirectResponse
    {
        $scores->snapshot($suppliers->findForCompany($request->user()->company_id, $supplier), $request->user()->id, 'Manual score recalculation from supplier dashboard.');

        return back()->with('status', 'Supplier score recalculated.');
    }

    public function productOptions(Request $request, ProductRepository $products, InventoryLookupRepository $lookups): View
    {
        return view('command-center.purchases.suppliers.product-options', [
            'products' => $products->activeForCompany($request->user()->company_id),
            'taxRates' => $lookups->formOptions($request->user()->company_id)['taxRates'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSupplier(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:80', Rule::unique('suppliers')->where('company_id', $request->user()->company_id)->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'supplier_type' => ['required', Rule::in(collect(SupplierType::cases())->pluck('value')->all())],
            'tax_id' => ['nullable', 'string', 'max:120'],
            'gstin' => ['nullable', 'string', 'max:120'],
            'pan' => ['nullable', 'string', 'max:120'],
            'website' => ['nullable', 'url', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'alternate_phone' => ['nullable', 'string', 'max:50'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'default_currency' => ['required', 'string', 'size:3'],
            'lead_time_days' => ['nullable', 'integer', 'min:0'],
            'manual_rating' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'service_notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}

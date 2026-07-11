<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ProductRequest;
use App\Models\Inventory\Product;
use App\Repositories\Inventory\InventoryLookupRepository;
use App\Repositories\Inventory\ProductRepository;
use App\Services\Inventory\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request, ProductRepository $products, InventoryLookupRepository $lookups): View
    {
        return view('command-center.inventory.products.index', [
            'products' => $products->paginateForCompany($request->user()->company_id, $request->only(['search', 'category_id', 'brand_id', 'status', 'trashed'])),
            'categories' => $lookups->categories($request->user()->company_id),
            'brands' => $lookups->brands($request->user()->company_id),
        ]);
    }

    public function create(Request $request, InventoryLookupRepository $lookups): View
    {
        return view('command-center.inventory.products.create', [
            'product' => new Product(['type' => 'simple', 'status' => Product::STATUS_ACTIVE, 'track_inventory' => true]),
            'options' => $lookups->formOptions($request->user()->company_id),
        ]);
    }

    public function store(ProductRequest $request, ProductService $products): RedirectResponse
    {
        $product = $products->create($request->user(), $request->validated());

        return redirect()->route('inventory.products.show', $product)->with('status', 'Product created.');
    }

    public function show(Request $request, ProductRepository $products, int $product): View
    {
        return view('command-center.inventory.products.show', [
            'product' => $products->findForCompany($request->user()->company_id, $product, true),
        ]);
    }

    public function edit(Request $request, ProductRepository $products, InventoryLookupRepository $lookups, int $product): View
    {
        return view('command-center.inventory.products.edit', [
            'product' => $products->findForCompany($request->user()->company_id, $product, true),
            'options' => $lookups->formOptions($request->user()->company_id),
        ]);
    }

    public function update(ProductRequest $request, ProductRepository $productRepository, ProductService $productService, int $product): RedirectResponse
    {
        $productService->update($productRepository->findForCompany($request->user()->company_id, $product), $request->user(), $request->validated());

        return back()->with('status', 'Product updated.');
    }

    public function destroy(Request $request, ProductRepository $productRepository, ProductService $productService, int $product): RedirectResponse
    {
        $productService->delete($productRepository->findForCompany($request->user()->company_id, $product));

        return redirect()->route('inventory.products.index')->with('status', 'Product moved to trash.');
    }

    public function restore(Request $request, ProductRepository $productRepository, ProductService $productService, int $product): RedirectResponse
    {
        $productService->restore($productRepository->findForCompany($request->user()->company_id, $product, true));

        return back()->with('status', 'Product restored.');
    }
}

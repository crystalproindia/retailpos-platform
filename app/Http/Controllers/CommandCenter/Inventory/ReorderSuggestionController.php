<?php

namespace App\Http\Controllers\CommandCenter\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\ReorderRule;
use App\Models\Inventory\ReorderSuggestion;
use App\Repositories\Inventory\InventoryLookupRepository;
use App\Repositories\Inventory\ProductRepository;
use App\Repositories\Inventory\ReorderRepository;
use App\Services\Inventory\ReorderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReorderSuggestionController extends Controller
{
    public function index(Request $request, ReorderRepository $reorders, ProductRepository $products, InventoryLookupRepository $lookups): View
    {
        $options = $lookups->formOptions($request->user()->company_id);

        return view('command-center.inventory.reorder.index', [
            'suggestions' => $reorders->suggestions($request->user()->company_id, $request->only(['status', 'risk'])),
            'rules' => ReorderRule::query()->with(['product', 'warehouse'])->where('company_id', $request->user()->company_id)->latest()->get(),
            'products' => $products->activeForCompany($request->user()->company_id),
            'warehouses' => $options['warehouses'],
        ]);
    }

    public function storeRule(Request $request, ReorderService $reorders): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'maximum_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['required', 'numeric', 'min:0'],
            'reorder_quantity' => ['required', 'numeric', 'min:0'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'supplier_lead_time_days' => ['nullable', 'integer', 'min:0'],
            'preferred_supplier_id' => ['nullable', 'integer'],
            'average_daily_sales' => ['nullable', 'numeric', 'min:0'],
            'seasonal_factor' => ['nullable', 'numeric', 'min:0'],
            'auto_generate_purchase_request' => ['nullable', 'boolean'],
            'requires_approval' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rule = $reorders->saveRule($request->user(), $validated);
        $reorders->generateSuggestion($rule, $request->user());

        return back()->with('status', 'Reorder rule saved and evaluated.');
    }

    public function generate(Request $request, ReorderService $reorders, int $rule): RedirectResponse
    {
        $model = ReorderRule::query()->where('company_id', $request->user()->company_id)->findOrFail($rule);
        $suggestion = $reorders->generateSuggestion($model, $request->user());

        return back()->with('status', $suggestion ? 'Reorder suggestion generated.' : 'Stock is above reorder point.');
    }

    public function review(Request $request, ReorderService $reorders, int $suggestion): RedirectResponse
    {
        $model = ReorderSuggestion::query()->where('company_id', $request->user()->company_id)->findOrFail($suggestion);
        $reorders->review($model, $request->user());

        return back()->with('status', 'Reorder suggestion reviewed.');
    }

    public function dismiss(Request $request, ReorderService $reorders, int $suggestion): RedirectResponse
    {
        $model = ReorderSuggestion::query()->where('company_id', $request->user()->company_id)->findOrFail($suggestion);
        $reorders->dismiss($model, $request->user());

        return back()->with('status', 'Reorder suggestion dismissed.');
    }
}

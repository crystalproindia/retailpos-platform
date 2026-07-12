<?php

namespace App\Http\Controllers\CommandCenter\Promotions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotions\PromotionSimulationRequest;
use App\Models\Branch;
use App\Models\Inventory\Product;
use App\Models\Inventory\SalesChannel;
use App\Repositories\Promotions\PromotionSimulationRepository;
use App\Services\Promotions\PromotionSimulationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionSimulatorController extends Controller
{
    public function index(Request $request, PromotionSimulationRepository $simulations): View
    {
        $company = $request->user()->company_id;
        return view('command-center.promotions.simulator.index', ['branches' => Branch::query()->where('company_id', $company)->where('is_active', true)->get(), 'channels' => SalesChannel::query()->where('company_id', $company)->where('is_active', true)->get(), 'products' => Product::query()->where('company_id', $company)->where('is_active', true)->orderBy('name')->get(), 'simulations' => $simulations->recentForCompany($company), 'result' => session('promotion_simulation')]);
    }

    public function run(PromotionSimulationRequest $request, PromotionSimulationService $service): RedirectResponse
    {
        $validated = $request->validated();
        $products = Product::query()->where('company_id', $request->user()->company_id)->whereIn('id', collect($validated['items'])->pluck('product_id'))->get()->keyBy('id');
        $validated['items'] = collect($validated['items'])->map(function (array $item) use ($products): array { $product = $products->get($item['product_id']); return $item + ['product_name' => $product->name, 'category_id' => $product->category_id, 'brand_id' => $product->brand_id]; })->all();
        $validated['bill_subtotal'] = array_sum(array_map(fn (array $item): float => (float) $item['quantity'] * (float) $item['unit_price'], $validated['items']));
        $result = $service->run($request->user(), $validated, $validated['title'] ?? null);
        return redirect()->route('promotions.simulator.index')->with('promotion_simulation', $result)->with('status', 'Promotion simulation completed.');
    }
}

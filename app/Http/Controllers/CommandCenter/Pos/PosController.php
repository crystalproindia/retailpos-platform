<?php

namespace App\Http\Controllers\CommandCenter\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosCheckoutRequest;
use App\Http\Requests\Pos\PosQuickCustomerRequest;
use App\Models\Customers\Customer;
use App\Repositories\Pos\PosCatalogRepository;
use App\Repositories\Pos\PosSaleRepository;
use App\Services\Pos\CustomerProductSuggestionService;
use App\Services\Pos\PosCheckoutService;
use App\Services\Pos\PosCustomerLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(Request $request, PosCatalogRepository $catalog, PosSaleRepository $sales): View
    {
        return view('command-center.pos.index', [
            'products' => $catalog->search($request->user()->company_id, $request->user()->branch_id, $request->string('search')->toString()),
            'heldSales' => $sales->heldForUser($request->user()->company_id, $request->user()->id),
            'resumedSale' => null,
        ]);
    }

    public function catalog(Request $request, PosCatalogRepository $catalog): JsonResponse
    {
        return response()->json(['products' => $catalog->search($request->user()->company_id, $request->user()->branch_id, $request->string('q')->toString())->map(fn ($product) => $this->productPayload($product))->values()]);
    }

    public function customer(Request $request, PosCustomerLookupService $lookup, CustomerProductSuggestionService $suggestions): JsonResponse
    {
        $request->validate(['mobile' => ['required', 'string', 'min:6', 'max:50']]);
        $customer = $lookup->findByMobile($request->user()->company_id, (string) $request->mobile);
        if (! $customer) return response()->json(['customer' => null, 'suggestions' => []]);

        return response()->json(['customer' => $this->customerPayload($customer), 'suggestions' => collect($suggestions->suggestions($customer, $request->user()->branch_id))->map(fn ($products) => $products->map(fn ($product) => $this->productPayload($product))->values())]);
    }

    public function quickCustomer(PosQuickCustomerRequest $request, PosCustomerLookupService $lookup, CustomerProductSuggestionService $suggestions): JsonResponse
    {
        $customer = $lookup->quickCreate($request->user(), $request->validated());

        return response()->json(['customer' => $this->customerPayload($customer), 'suggestions' => collect($suggestions->suggestions($customer, $request->user()->branch_id))->map(fn ($products) => $products->map(fn ($product) => $this->productPayload($product))->values())], 201);
    }

    public function hold(PosCheckoutRequest $request, PosCheckoutService $checkout): RedirectResponse
    {
        $sale = $checkout->hold($request->user(), $request->validated());

        return redirect()->route('pos.index')->with('status', "Bill {$sale->sale_number} is on hold.");
    }

    public function complete(PosCheckoutRequest $request, PosCheckoutService $checkout): RedirectResponse
    {
        $sale = $checkout->complete($request->user(), $request->validated());

        return redirect()->route('pos.receipts.show', $sale)->with('status', "Sale {$sale->sale_number} completed.");
    }

    public function resume(Request $request, PosSaleRepository $sales, int $sale): View
    {
        $resumedSale = $sales->findForCompany($request->user()->company_id, $sale);
        abort_unless($resumedSale->status === 'held' && $resumedSale->held_by === $request->user()->id, 403);

        return view('command-center.pos.index', ['products' => app(PosCatalogRepository::class)->search($request->user()->company_id, $request->user()->branch_id), 'heldSales' => $sales->heldForUser($request->user()->company_id, $request->user()->id)->where('id', '!=', $resumedSale->id), 'resumedSale' => $resumedSale]);
    }

    public function receipt(Request $request, PosSaleRepository $sales, int $sale): View
    {
        $sale = $sales->findForCompany($request->user()->company_id, $sale);
        abort_unless($sale->status === 'completed', 404);

        return view('command-center.pos.receipt', compact('sale'));
    }

    /** @return array<string, mixed> */
    private function customerPayload(Customer $customer): array
    {
        return ['id' => $customer->id, 'name' => $customer->display_name, 'mobile' => $customer->phone ?: $customer->whatsapp, 'group' => $customer->groups->first()?->group?->name, 'loyalty_points' => $customer->loyalty_points_balance, 'wallet_balance' => (float) $customer->wallet_balance, 'last_purchase_at' => $customer->last_purchase_at?->toDateString(), 'birthday' => $customer->date_of_birth?->format('d M'), 'retention_note' => $customer->insight?->segment_label ? $customer->insight->segment_label.' - '.$customer->insight->retention_risk_score.' retention risk' : 'No retention signal yet'];
    }

    /** @return array<string, mixed> */
    private function productPayload($product): array
    {
        return ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode, 'price' => (float) $product->selling_price, 'category' => $product->category?->name, 'category_id' => $product->category_id, 'available_stock' => (float) $product->stockLevels->sum('quantity_available')];
    }
}

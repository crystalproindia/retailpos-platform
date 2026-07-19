<?php

namespace App\Http\Controllers\CommandCenter\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\PosCheckoutRequest;
use App\Http\Requests\Pos\PosQuickCustomerRequest;
use App\Models\Customers\Customer;
use App\Models\Pos\PosRegister;
use App\Models\Pos\PosSale;
use App\Repositories\Pos\PosCatalogRepository;
use App\Repositories\Pos\PosSaleRepository;
use App\Services\Pos\CustomerProductSuggestionService;
use App\Services\Pos\PosCheckoutService;
use App\Services\Pos\PosCustomerLookupService;
use App\Services\Pos\PosDashboardService;
use App\Services\Pos\PosReceiptPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\Response;

class PosController extends Controller
{
    public function index(Request $request, PosCatalogRepository $catalog, PosSaleRepository $sales, PosDashboardService $dashboard): View
    {
        return $this->workspace($request, $catalog, $sales, $dashboard);
    }

    public function terminal(Request $request, PosCatalogRepository $catalog, PosSaleRepository $sales, PosDashboardService $dashboard): View
    {
        return $this->workspace($request, $catalog, $sales, $dashboard, 'terminal');
    }

    public function mobile(Request $request, PosCatalogRepository $catalog, PosSaleRepository $sales, PosDashboardService $dashboard): View
    {
        return $this->workspace($request, $catalog, $sales, $dashboard, 'mobile');
    }

    public function dashboard(Request $request, PosDashboardService $dashboard): View
    {
        return view('command-center.pos.dashboard', ['summary' => $dashboard->summary($request->user()->company_id, $request->user()->branch_id)]);
    }

    public function heldBills(Request $request, PosSaleRepository $sales): View
    {
        return view('command-center.pos.held', ['heldSales' => $sales->heldForUser($request->user()->company_id, $request->user()->id, $request->string('q')->toString())]);
    }

    public function salesHistory(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:completed,voided'],
            'payment_method' => ['nullable', 'in:cash,card,upi,bank_transfer,wallet,credit,other'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $sales = PosSale::query()
            ->with(['branch', 'register', 'customer', 'completer', 'payments'])
            ->where('company_id', $request->user()->company_id)
            ->whereIn('status', ['completed', 'voided'])
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($sales) use ($search): void {
                    $sales->where('receipt_number', 'like', "%{$search}%")
                        ->orWhere('sale_number', 'like', "%{$search}%")
                        ->orWhere('customer_name_snapshot', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('display_name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['payment_method'] ?? null, fn ($query, string $method) => $query->whereHas('payments', fn ($payments) => $payments->where('payment_method', $method)))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('completed_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('completed_at', '<=', $to))
            ->latest('completed_at')
            ->paginate(25)
            ->withQueryString();

        return view('command-center.pos.sales.index', compact('sales', 'filters'));
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

    public function resume(Request $request, PosSaleRepository $sales, PosCatalogRepository $catalog, PosDashboardService $dashboard, int $sale): View
    {
        $resumedSale = $sales->findForCompany($request->user()->company_id, $sale);
        abort_unless($resumedSale->status === 'held' && $resumedSale->held_by === $request->user()->id, 403);

        return $this->workspace($request, $catalog, $sales, $dashboard, 'terminal', $resumedSale);
    }

    public function receipt(Request $request, PosSaleRepository $sales, int $sale): View
    {
        $sale = $sales->findForCompany($request->user()->company_id, $sale);
        abort_unless(in_array($sale->status, ['completed', 'voided'], true), 404);

        return view('command-center.pos.receipt', compact('sale'));
    }

    public function receiptPdf(Request $request, PosSaleRepository $sales, PosReceiptPdfService $pdf, int $sale): Response
    {
        $sale = $sales->findForCompany($request->user()->company_id, $sale);
        abort_unless(in_array($sale->status, ['completed', 'voided'], true), 404);

        return $pdf->document($sale)->download($pdf->filename($sale));
    }

    public function void(Request $request, PosSaleRepository $sales, PosCheckoutService $checkout, int $sale): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $sale = $checkout->void($sales->findForCompany($request->user()->company_id, $sale), $request->user(), $data['reason']);

        return redirect()->route('pos.receipts.show', $sale)->with('status', 'Sale voided and stock restored.');
    }

    /** @return array<string, mixed> */
    private function customerPayload(Customer $customer): array
    {
        return ['id' => $customer->id, 'name' => $customer->display_name, 'mobile' => $customer->phone ?: $customer->whatsapp, 'group' => $customer->groups->first()?->group?->name, 'loyalty_points' => $customer->loyalty_points_balance, 'wallet_balance' => (float) $customer->wallet_balance, 'last_purchase_at' => $customer->last_purchase_at?->toDateString(), 'birthday' => $customer->date_of_birth?->format('d M'), 'retention_note' => $customer->insight?->segment_label ? $customer->insight->segment_label.' - '.$customer->insight->retention_risk_score.' retention risk' : 'No retention signal yet'];
    }

    /** @return array<string, mixed> */
    private function workspace(Request $request, PosCatalogRepository $catalog, PosSaleRepository $sales, PosDashboardService $dashboard, string $mode = 'desktop', mixed $resumedSale = null): View
    {
        $products = $catalog->search($request->user()->company_id, $request->user()->branch_id, $request->string('search')->toString());

        return view('command-center.pos.index', [
            'products' => $products,
            'categories' => $products->pluck('category')->filter()->unique('id')->values(),
            'heldSales' => $sales->heldForUser($request->user()->company_id, $request->user()->id),
            'resumedSale' => $resumedSale,
            'posMode' => $mode,
            'popularProductIds' => $dashboard->popularProductIds($request->user()->company_id, $request->user()->branch_id),
            'openRegisters' => PosRegister::query()->where('company_id', $request->user()->company_id)->where('branch_id', $request->user()->branch_id)->where('is_active', true)->whereNotNull('current_session_id')->orderBy('name')->get(),
        ]);
    }

    private function productPayload($product): array
    {
        $stock = (float) $product->stockLevels->sum('quantity_available');

        return ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'barcode' => $product->barcode, 'price' => (float) $product->selling_price, 'category' => $product->category?->name, 'brand' => $product->brand?->name, 'category_id' => $product->category_id, 'image' => $product->image, 'track_inventory' => (bool) $product->track_inventory, 'available_stock' => $stock, 'low_stock' => $product->track_inventory && $stock > 0 && $stock <= 5];
    }
}

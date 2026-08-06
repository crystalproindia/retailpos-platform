<?php

namespace App\Http\Controllers\CommandCenter\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pos\StorePosReturnRequest;
use App\Http\Requests\Pos\UpdatePosReturnSettingsRequest;
use App\Models\Pos\PosReturn;
use App\Services\AuditLogger;
use App\Services\Pos\PosReturnPdfService;
use App\Services\Pos\PosReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PosReturnController extends Controller
{
    public function index(Request $request, PosReturnService $returns): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $sales = $returns->lookup($request->user(), $filters);
        $recentReturns = $returns->findForUser($request->user(), 0, true)->with(['originalSale', 'customer'])->latest()->paginate(20)->withQueryString();

        return view('command-center.pos.returns.index', compact('sales', 'recentReturns', 'filters'));
    }

    public function create(Request $request, PosReturnService $returns): View
    {
        abort_unless($request->integer('sale'), 404);
        $sale = $returns->saleForReturn($request->user(), $request->integer('sale'), $request->boolean('window_override'));
        $settings = $returns->settings($request->user()->company_id);

        return view('command-center.pos.returns.create', compact('sale', 'settings'));
    }

    public function store(StorePosReturnRequest $request, PosReturnService $returns): RedirectResponse
    {
        $sale = $returns->saleForReturn($request->user(), (int) $request->sale_id, $request->boolean('window_override'));
        $return = $returns->create($request->user(), $sale, $request->validated());

        return redirect()->route('pos.returns.show', $return)->with('status', $return->status === PosReturn::STATUS_PENDING_APPROVAL ? 'Return submitted for manager approval.' : 'Return created and ready to complete.');
    }

    public function preview(Request $request, PosReturnService $returns): JsonResponse
    {
        abort_unless($request->user()?->can('pos.returns.create'), 403);
        $data = $request->validate(['sale_id' => ['required', 'integer'], 'items' => ['required', 'array'], 'items.*.original_sale_item_id' => ['required', 'integer'], 'items.*.return_quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'], 'items.*.stock_disposition' => ['nullable', 'in:restock,damaged,scrap,quarantine,no_stock_change'], 'items.*.condition_note' => ['nullable', 'string', 'max:1000']]);
        $sale = $returns->saleForReturn($request->user(), (int) $data['sale_id']);
        $preview = $returns->preview($sale, $data['items']);

        return response()->json(['subtotal' => $preview['gross_minor'] / 100, 'discount_adjustment' => $preview['discount_minor'] / 100, 'taxable_adjustment' => $preview['taxable_minor'] / 100, 'tax_adjustment' => $preview['tax_minor'] / 100, 'refund_total' => $preview['refund_total_minor'] / 100]);
    }

    public function show(Request $request, PosReturnService $returns, int $posReturn): View
    {
        return view('command-center.pos.returns.show', ['return' => $returns->findForUser($request->user(), $posReturn)]);
    }

    public function approve(Request $request, PosReturnService $returns, int $posReturn): RedirectResponse
    {
        abort_unless($request->user()->can('pos.returns.approve'), 403);
        $return = $returns->approve($request->user(), $returns->findForUser($request->user(), $posReturn));

        return back()->with('status', "Return {$return->return_number} approved.");
    }

    public function complete(Request $request, PosReturnService $returns, int $posReturn): RedirectResponse
    {
        abort_unless($request->user()->can('pos.returns.complete'), 403);
        $return = $returns->complete($request->user(), $returns->findForUser($request->user(), $posReturn));

        return redirect()->route('pos.returns.show', $return)->with('status', "Return {$return->return_number} completed.");
    }

    public function reject(Request $request, PosReturnService $returns, int $posReturn): RedirectResponse
    {
        abort_unless($request->user()->can('pos.returns.approve'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $return = $returns->reject($request->user(), $returns->findForUser($request->user(), $posReturn), $data['reason']);

        return back()->with('status', "Return {$return->return_number} rejected.");
    }

    public function cancel(Request $request, PosReturnService $returns, int $posReturn): RedirectResponse
    {
        abort_unless($request->user()->can('pos.returns.cancel'), 403);
        $return = $returns->cancel($request->user(), $returns->findForUser($request->user(), $posReturn));

        return redirect()->route('pos.returns.show', $return)->with('status', "Return {$return->return_number} cancelled. No stock or refund was posted.");
    }

    public function pdf(Request $request, PosReturnService $returns, PosReturnPdfService $pdf, int $posReturn): Response
    {
        $return = $returns->findForUser($request->user(), $posReturn);
        abort_unless($return->status === PosReturn::STATUS_COMPLETED, 404);
        app(AuditLogger::class)->record('pos.return.credit_note_printed', $return, 'POS return credit note downloaded', ['company_id' => $request->user()->company_id]);

        return $pdf->document($return)->download($pdf->filename($return));
    }

    public function settings(Request $request, PosReturnService $returns): View
    {
        return view('command-center.pos.returns.settings', ['settings' => $returns->settings($request->user()->company_id)]);
    }

    public function updateSettings(UpdatePosReturnSettingsRequest $request, PosReturnService $returns): RedirectResponse
    {
        $returns->updateSettings($request->user(), $request->validated());

        return back()->with('status', 'Return controls updated.');
    }
}

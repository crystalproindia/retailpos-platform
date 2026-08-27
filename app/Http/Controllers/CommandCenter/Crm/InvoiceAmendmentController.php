<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreInvoiceAmendmentRequest;
use App\Repositories\Crm\InvoiceRepository;
use App\Services\Crm\InvoiceAmendmentService;
use App\Services\Inventory\InventoryLocationAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceAmendmentController extends Controller
{
    public function create(Request $request, InvoiceRepository $invoices, InvoiceAmendmentService $amendments, InventoryLocationAccessService $locations, int $invoice): View
    {
        $record = $invoices->find($request->user(), $invoice);
        $amendments->assertEligible($record, $request->user());
        $warehouses = $locations->accessibleWarehouses($request->user())
            ->where('branch_id', $record->branch_id)
            ->values();

        return view('command-center.crm.invoices.amend', compact('record', 'warehouses'));
    }

    public function store(StoreInvoiceAmendmentRequest $request, InvoiceAmendmentService $amendments, int $invoice): RedirectResponse
    {
        $amendment = $amendments->finalize($request->user(), $invoice, $request->validated());

        return redirect()->route('sales.invoices.show', $amendment->invoice_id)
            ->with('status', 'Invoice amendment confirmed. Version '.$amendment->version_to.' is now authoritative.');
    }
}

<?php

namespace App\Http\Controllers\CommandCenter\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Purchases\PurchaseInvoice;
use App\Models\Purchases\Supplier;
use App\Services\Purchases\SupplierPayableService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseReportController extends Controller
{
    public function index(Request $request, SupplierPayableService $payables): View
    {
        $companyId = $request->user()->company_id;
        $invoices = $this->invoices($request)->paginate(50)->withQueryString();
        return view('command-center.purchases.reports.index', [
            'invoices' => $invoices,
            'suppliers' => Supplier::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'payable' => $payables->summary($companyId, $request->integer('supplier_id') ?: null),
            'ageing' => $payables->ageing($companyId, $request->integer('supplier_id') ?: null),
        ]);
    }

    public function inputGst(Request $request): View
    {
        $invoices = $this->invoices($request)->with('supplier')->paginate(50)->withQueryString();
        return view('command-center.purchases.reports.input-gst', [
            'invoices' => $invoices,
            'totals' => ['cgst' => $invoices->sum('input_cgst'), 'sgst' => $invoices->sum('input_sgst'), 'igst' => $invoices->sum('input_igst'), 'cess' => $invoices->sum('input_cess')],
        ]);
    }

    private function invoices(Request $request)
    {
        return PurchaseInvoice::query()->with('supplier')->where('company_id', $request->user()->company_id)->whereIn('status', ['approved', 'partially_paid', 'paid', 'overdue'])
            ->when($request->supplier_id, fn ($query, $supplier) => $query->where('supplier_id', $supplier))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('supplier_invoice_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('supplier_invoice_date', '<=', $request->date('to')))
            ->when($request->has('reverse_charge'), fn ($query) => $query->where('reverse_charge', $request->boolean('reverse_charge')))
            ->latest('supplier_invoice_date');
    }
}

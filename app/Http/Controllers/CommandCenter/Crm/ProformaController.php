<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\ProformaStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreProformaPaymentRequest;
use App\Http\Requests\Crm\StoreProformaRequest;
use App\Models\Crm\CrmCustomer;
use App\Models\Crm\CrmQuotation;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Repositories\Crm\ProformaRepository;
use App\Repositories\Crm\QuotationRepository;
use App\Services\Crm\ProformaPdfService;
use App\Services\Crm\ProformaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ProformaController extends Controller
{
    public function index(Request $request, ProformaRepository $proformas): View
    {
        return view('command-center.crm.proformas.index', [
            'proformas' => $proformas->paginate($request->user(), $request->only('search', 'status')),
            'statuses' => ProformaStatus::cases(),
        ]);
    }

    public function create(Request $request, CrmCustomerRepository $customers): View
    {
        $customerId = $request->integer('customer_id') ?: null;
        $customer = $customerId ? $customers->findForUser($request->user(), $customerId) : null;

        return $this->form($request, $customer?->lead_id, $customer?->id, $this->quotationForCustomer($customer), $customer, $customers);
    }

    public function createFromQuotation(Request $request, QuotationRepository $quotations, CrmCustomerRepository $customers, int $quotation): View
    {
        $record = $quotations->findForUser($request->user(), $quotation);
        abort_unless($record->status?->value === 'accepted', 422);

        return $this->form($request, $record->lead_id, $record->crmCustomer?->id, $record, $record->crmCustomer, $customers);
    }

    public function createFromCustomer(Request $request, CrmCustomerRepository $customers, int $customer): View
    {
        $record = $customers->findForUser($request->user(), $customer);

        return $this->form($request, $record->lead_id, $record->id, $this->quotationForCustomer($record), $record, $customers);
    }

    public function store(StoreProformaRequest $request, ProformaService $proformas): RedirectResponse
    {
        $proforma = $proformas->create(
            $request->user(),
            $request->validated(),
            $request->integer('lead_id') ?: null,
            $request->integer('customer_id') ?: null,
            $request->integer('quotation_id') ?: null,
        );

        return redirect()->route('crm.proformas.show', $proforma)->with('status', 'Proforma invoice created.');
    }

    public function show(Request $request, ProformaRepository $proformas, int $proforma): View
    {
        return view('command-center.crm.proformas.show', ['proforma' => $proformas->find($request->user(), $proforma)]);
    }

    public function payment(StoreProformaPaymentRequest $request, ProformaRepository $proformas, ProformaService $service, int $proforma): RedirectResponse
    {
        $service->payment($proformas->find($request->user(), $proforma), $request->user(), $request->validated());

        return back()->with('status', 'Payment recorded.');
    }

    public function sent(Request $request, ProformaRepository $proformas, ProformaService $service, int $proforma): RedirectResponse
    {
        $service->markSent($proformas->find($request->user(), $proforma), $request->user());

        return back()->with('status', 'Proforma marked as sent.');
    }

    public function link(Request $request, ProformaRepository $proformas, ProformaService $service, int $proforma): RedirectResponse
    {
        $service->link($proformas->find($request->user(), $proforma), $request->user(), $request->boolean('regenerate'));

        return back()->with('status', 'Secure public link ready.');
    }

    public function pdf(Request $request, ProformaRepository $proformas, ProformaPdfService $pdf, int $proforma): Response
    {
        $record = $proformas->find($request->user(), $proforma);

        return $pdf->document($record)->download($pdf->filename($record));
    }

    public function preview(Request $request, ProformaRepository $proformas, ProformaPdfService $pdf, int $proforma): Response
    {
        $record = $proformas->find($request->user(), $proforma);

        return $pdf->document($record)->stream($pdf->filename($record));
    }

    private function quotationForCustomer(?CrmCustomer $customer): CrmQuotation
    {
        return $customer?->quotation ?: new CrmQuotation([
            'title' => 'Proforma Invoice',
            'customer_name' => $customer?->display_name,
            'customer_company' => $customer?->company_name,
            'customer_email' => $customer?->email,
            'customer_phone' => $customer?->phone,
            'billing_address' => $customer?->billing_address,
            'currency' => 'INR',
        ]);
    }

    private function form(Request $request, ?int $leadId, ?int $customerId, CrmQuotation $quotation, ?CrmCustomer $customer, CrmCustomerRepository $customers): View
    {
        $items = $quotation->items->map(fn ($item) => $item->only(['name', 'description', 'quantity', 'unit_price', 'discount_amount', 'tax_rate']))->all()
            ?: [['name' => '', 'description' => '', 'quantity' => 1, 'unit_price' => 0, 'discount_amount' => 0, 'tax_rate' => 0]];

        return view('command-center.crm.proformas.form', [
            'quotation' => $quotation,
            'items' => $items,
            'leadId' => $leadId,
            'customerId' => $customerId,
            'selectedCustomer' => $customer,
            'customers' => $customers->selectableForDocument($request->user()),
        ]);
    }
}

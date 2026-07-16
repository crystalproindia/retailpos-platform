<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Enums\Crm\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreQuotationRequest;
use App\Http\Requests\Crm\UpdateQuotationRequest;
use App\Models\Crm\CrmQuotation;
use App\Repositories\Crm\LeadRepository;
use App\Repositories\Crm\QuotationRepository;
use App\Services\Crm\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request, QuotationRepository $quotationRepository): View
    {
        return view('command-center.crm.quotations.index', [
            'quotations' => $quotationRepository->paginateForUser($request->user(), $request->only(['search', 'status'])),
            'statuses' => QuotationStatus::cases(),
        ]);
    }

    public function create(Request $request, LeadRepository $leadRepository, int $lead): View
    {
        $crmLead = $leadRepository->findForUser($request->user(), $lead);

        return view('command-center.crm.quotations.form', [
            'quotation' => new CrmQuotation([
                'title' => 'Proposal for '.($crmLead->business_name ?? $crmLead->contact_name ?? $crmLead->title),
                'customer_name' => $crmLead->contact_name,
                'customer_company' => $crmLead->business_name,
                'customer_email' => $crmLead->email,
                'customer_phone' => $crmLead->phone,
                'currency' => $crmLead->currency ?? 'INR',
                'valid_until' => now()->addDays(14),
            ]),
            'lead' => $crmLead,
            'items' => [self::emptyItem()],
        ]);
    }

    public function store(StoreQuotationRequest $request, LeadRepository $leadRepository, QuotationService $quotationService, int $lead): RedirectResponse
    {
        $quotation = $quotationService->create($leadRepository->findForUser($request->user(), $lead), $request->user(), $request->validated());

        return redirect()->route('crm.quotations.show', $quotation)->with('status', 'Quotation created.');
    }

    public function show(Request $request, QuotationRepository $quotationRepository, int $quotation): View
    {
        return view('command-center.crm.quotations.show', [
            'quotation' => $quotationRepository->findForUser($request->user(), $quotation),
        ]);
    }

    public function edit(Request $request, QuotationRepository $quotationRepository, int $quotation): View
    {
        $crmQuotation = $quotationRepository->findForUser($request->user(), $quotation);
        abort_unless($crmQuotation->status?->isEditable(), 422, 'Only draft quotations can be edited.');

        return view('command-center.crm.quotations.form', [
            'quotation' => $crmQuotation,
            'lead' => $crmQuotation->lead,
            'items' => $crmQuotation->items->map(fn ($item) => $item->only(['name', 'description', 'quantity', 'unit_price', 'discount_amount', 'tax_rate']))->all(),
        ]);
    }

    public function update(UpdateQuotationRequest $request, QuotationRepository $quotationRepository, QuotationService $quotationService, int $quotation): RedirectResponse
    {
        $updated = $quotationService->update($quotationRepository->findForUser($request->user(), $quotation), $request->user(), $request->validated());

        return redirect()->route('crm.quotations.show', $updated)->with('status', 'Quotation updated.');
    }

    public function send(Request $request, QuotationRepository $quotationRepository, QuotationService $quotationService, int $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('crm.quotations.send'), 403);
        $updated = $quotationService->markSent($quotationRepository->findForUser($request->user(), $quotation), $request->user());

        return redirect()->route('crm.quotations.show', $updated)->with('status', 'Quotation marked as sent.');
    }

    public function accept(Request $request, QuotationRepository $quotationRepository, QuotationService $quotationService, int $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('crm.quotations.accept'), 403);
        $updated = $quotationService->markAccepted($quotationRepository->findForUser($request->user(), $quotation), $request->user());

        return redirect()->route('crm.quotations.show', $updated)->with('status', 'Quotation marked as accepted.');
    }

    public function reject(Request $request, QuotationRepository $quotationRepository, QuotationService $quotationService, int $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('crm.quotations.reject'), 403);
        $updated = $quotationService->markRejected($quotationRepository->findForUser($request->user(), $quotation), $request->user());

        return redirect()->route('crm.quotations.show', $updated)->with('status', 'Quotation marked as rejected.');
    }

    public function convert(Request $request, QuotationRepository $quotationRepository, QuotationService $quotationService, int $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('crm.quotations.update'), 403);
        $updated = $quotationService->markConverted($quotationRepository->findForUser($request->user(), $quotation), $request->user());

        return redirect()->route('crm.quotations.show', $updated)->with('status', 'Quotation prepared for the customer conversion workflow.');
    }

    public function publicLink(Request $request, QuotationRepository $quotationRepository, QuotationService $quotationService, int $quotation): RedirectResponse
    {
        abort_unless($request->user()->can('crm.quotations.update'), 403);
        $updated = $quotationService->generatePublicLink($quotationRepository->findForUser($request->user(), $quotation), $request->user());

        return redirect()->route('crm.quotations.show', $updated)->with('status', 'Secure public quotation link is ready.');
    }

    /** @return array<string, int|float|string|null> */
    private static function emptyItem(): array
    {
        return ['name' => '', 'description' => null, 'quantity' => 1, 'unit_price' => 0, 'discount_amount' => 0, 'tax_rate' => 0];
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\Crm\PublicQuotationDecisionRequest;
use App\Services\Crm\PublicQuotationService;
use App\Services\Crm\QuotationPdfService;
use App\Services\Crm\SalesDocumentPresentationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PublicQuotationController extends Controller
{
    public function show(Request $request, PublicQuotationService $publicQuotations, SalesDocumentPresentationService $presentations, string $publicToken): Response
    {
        $quotation = $publicQuotations->recordView($publicQuotations->find($publicToken));

        return response()
            ->view('public.quotation', [
                'quotation' => $quotation,
                'publicToken' => $publicToken,
                'presentation' => $presentations->forDocument($quotation, SalesDocumentPresentationService::QUOTATION),
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function pdf(PublicQuotationService $publicQuotations, QuotationPdfService $pdf, string $publicToken): Response
    {
        $quotation = $publicQuotations->find($publicToken);

        return $pdf->document($quotation)->download($pdf->filename($quotation));
    }

    public function respond(PublicQuotationDecisionRequest $request, PublicQuotationService $publicQuotations, string $publicToken): RedirectResponse
    {
        $quotation = $publicQuotations->find($publicToken);
        $publicQuotations->respond($quotation, $request->validated('decision'), $request->validated(), $request->ip());

        return redirect()->route('quotations.public.show', $publicToken)->with('status', 'Thank you. Your decision has been recorded.');
    }
}

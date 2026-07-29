<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\SendQuotationEmailRequest;
use App\Repositories\Crm\QuotationRepository;
use App\Services\Crm\QuotationPdfService;
use App\Services\Crm\QuotationShareService;
use App\Services\Crm\QuotationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class QuotationShareController extends Controller
{
    public function downloadPdf(Request $request, QuotationRepository $quotations, QuotationPdfService $pdf, QuotationShareService $sharing, int $quotation): Response
    {
        $crmQuotation = $quotations->findForUser($request->user(), $quotation);
        $sharing->recordPdfDownload($crmQuotation, $request->user());

        return $pdf->document($crmQuotation)->download($pdf->filename($crmQuotation));
    }

    public function previewPdf(Request $request, QuotationRepository $quotations, QuotationPdfService $pdf, int $quotation): Response
    {
        $crmQuotation = $quotations->findForUser($request->user(), $quotation);

        return $pdf->document($crmQuotation)->stream($pdf->filename($crmQuotation));
    }

    public function createEmail(Request $request, QuotationRepository $quotations, QuotationShareService $sharing, QuotationService $quotationService, int $quotation): View
    {
        $link = $quotationService->issuePublicLink($quotations->findForUser($request->user(), $quotation), $request->user());

        return view('command-center.crm.quotations.email', [
            'quotation' => $link->quotation,
            'defaults' => $sharing->emailDefaults($link->quotation, $link->url),
        ]);
    }

    public function sendEmail(SendQuotationEmailRequest $request, QuotationRepository $quotations, QuotationShareService $sharing, int $quotation): RedirectResponse
    {
        $crmQuotation = $quotations->findForUser($request->user(), $quotation);
        $result = $sharing->sendEmail($crmQuotation, $request->user(), $request->validated(), $request->ccRecipients());

        if (! $result['configured']) {
            return redirect()->route('crm.quotations.show', $crmQuotation)->with('error', 'Quotation saved. Email delivery was skipped because SMTP is not configured.');
        }

        $message = $result['queued'] ? 'Proposal email queued for delivery.' : 'Proposal email delivery is being processed.';
        if ($result['attachment_unavailable']) {
            $message .= ' The secure proposal link was sent without the PDF attachment.';
        }

        return redirect()->route('crm.quotations.show', $crmQuotation)->with('status', $message);
    }

    public function whatsapp(Request $request, QuotationRepository $quotations, QuotationShareService $sharing, int $quotation): RedirectResponse
    {
        $payload = $sharing->prepareWhatsApp($quotations->findForUser($request->user(), $quotation), $request->user());

        if ($payload['url']) {
            return redirect()->away($payload['url']);
        }

        return redirect()->route('crm.quotations.show', $quotation)
            ->with('status', 'WhatsApp message prepared. Add a customer phone number to open WhatsApp directly.')
            ->with('whatsappMessage', $payload['message']);
    }
}

<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\SendProformaEmailRequest;
use App\Repositories\Crm\ProformaRepository;
use App\Services\Crm\ProformaService;
use App\Services\Crm\ProformaShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProformaShareController extends Controller
{
    public function createEmail(Request $request, ProformaRepository $proformas, ProformaShareService $sharing, ProformaService $service, int $proforma): View
    {
        $invoice = $service->link($proformas->find($request->user(), $proforma), $request->user());

        return view('command-center.crm.proformas.email', [
            'proforma' => $invoice,
            'defaults' => $sharing->emailDefaults($invoice),
        ]);
    }

    public function sendEmail(SendProformaEmailRequest $request, ProformaRepository $proformas, ProformaShareService $sharing, int $proforma): RedirectResponse
    {
        $invoice = $proformas->find($request->user(), $proforma);
        $result = $sharing->sendEmail($invoice, $request->user(), $request->validated(), $request->ccRecipients());

        if (! $result['sent']) {
            return redirect()->route('crm.proformas.show', $invoice)
                ->with('error', 'We could not send this proforma email. Check mail configuration and try again.');
        }

        $message = 'Proforma email sent successfully.';
        if ($result['attachment_unavailable']) {
            $message .= ' The secure proforma link was sent without the PDF attachment.';
        }

        return redirect()->route('crm.proformas.show', $invoice)->with('status', $message);
    }

    public function whatsapp(Request $request, ProformaRepository $proformas, ProformaShareService $sharing, int $proforma): RedirectResponse
    {
        $payload = $sharing->prepareWhatsApp($proformas->find($request->user(), $proforma), $request->user());

        return redirect()->route('crm.proformas.show', $proforma)
            ->with('status', $payload['url'] ? 'WhatsApp message prepared.' : 'WhatsApp message prepared. Add a customer phone number to open WhatsApp directly.')
            ->with('whatsappPayload', $payload);
    }
}

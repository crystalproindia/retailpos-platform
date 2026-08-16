<?php

namespace App\Http\Controllers;

use App\Models\Crm\CrmProformaInvoice;
use App\Services\Crm\SalesDocumentPresentationService;
use Illuminate\View\View;

class PublicProformaController extends Controller
{
    public function show(SalesDocumentPresentationService $presentations, string $token): View
    {
        $proforma = CrmProformaInvoice::query()->where('public_token', $token)->with('items')->firstOrFail();
        $presentation = $presentations->forDocument($proforma, SalesDocumentPresentationService::PROFORMA);

        return view('public.proforma', compact('proforma', 'presentation'));
    }
}

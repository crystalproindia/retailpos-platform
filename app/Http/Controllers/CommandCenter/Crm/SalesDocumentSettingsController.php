<?php

namespace App\Http\Controllers\CommandCenter\Crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\SalesDocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesDocumentSettingsController extends Controller
{
    public function index(Request $request, SalesDocumentNumberService $numbers): View
    {
        $company = $request->user()->company;

        return view('command-center.crm.invoices.document-settings', [
            'setting' => $numbers->setting($company),
            'previews' => $numbers->previews($company),
        ]);
    }

    public function update(Request $request, SalesDocumentNumberService $numbers): RedirectResponse
    {
        $data = $request->validate([
            'invoice_prefix' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'],
            'quotation_prefix' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'],
            'proforma_prefix' => ['nullable', 'string', 'max:24', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'],
        ]);
        $numbers->update($request->user()->company, $request->user(), $data);

        return back()->with('status', 'Document prefixes saved. Existing document numbers remain unchanged.');
    }
}

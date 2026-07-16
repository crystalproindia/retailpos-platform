<?php

namespace App\Http\Controllers;

use App\Models\Crm\CrmQuotation;
use Illuminate\View\View;

class PublicQuotationController extends Controller
{
    public function show(string $publicToken): View
    {
        $quotation = CrmQuotation::query()
            ->where('public_token', $publicToken)
            ->with(['company', 'lead', 'items'])
            ->firstOrFail();

        return view('public.quotation', compact('quotation'));
    }
}

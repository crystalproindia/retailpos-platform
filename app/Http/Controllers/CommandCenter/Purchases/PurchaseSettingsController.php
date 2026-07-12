<?php

namespace App\Http\Controllers\CommandCenter\Purchases;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Purchases\PurchaseNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseSettingsController extends Controller
{
    public function index(Request $request, PurchaseNumberService $numbers): View
    {
        return view('command-center.purchases.settings.index', [
            'settings' => $numbers->settings($request->user()->company_id),
        ]);
    }

    public function update(Request $request, PurchaseNumberService $numbers, AuditLogger $auditLogger): RedirectResponse
    {
        $settings = $numbers->settings($request->user()->company_id);
        $validated = $request->validate([
            'po_prefix' => ['required', 'string', 'max:20'],
            'pr_prefix' => ['required', 'string', 'max:20'],
            'grn_prefix' => ['required', 'string', 'max:20'],
            'return_prefix' => ['required', 'string', 'max:20'],
            'require_po_approval' => ['nullable', 'boolean'],
            'require_purchase_request_approval' => ['nullable', 'boolean'],
            'require_return_approval' => ['nullable', 'boolean'],
            'default_payment_terms' => ['nullable', 'string', 'max:255'],
            'default_tax_inclusive' => ['nullable', 'boolean'],
            'allow_receive_without_po' => ['nullable', 'boolean'],
            'auto_create_pr_from_reorder' => ['nullable', 'boolean'],
        ]);

        foreach (['require_po_approval', 'require_purchase_request_approval', 'require_return_approval', 'default_tax_inclusive', 'allow_receive_without_po', 'auto_create_pr_from_reorder'] as $key) {
            $validated[$key] = (bool) ($validated[$key] ?? false);
        }

        $settings->update($validated);

        $auditLogger->record('purchase.settings.updated', $settings, 'Purchase settings updated');

        return back()->with('status', 'Purchase settings updated.');
    }
}

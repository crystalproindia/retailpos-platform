<?php

namespace App\Http\Controllers\CommandCenter\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\GstSetting;
use App\Services\AuditLogger;
use App\Services\Compliance\GstinValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class GstSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $settings = GstSetting::firstOrCreate(['company_id' => $request->user()->company_id], ['legal_name' => $request->user()->company?->legal_name ?: $request->user()->company?->name ?: config('app.name')]);

        return view('command-center.compliance.gst.settings', compact('settings'));
    }

    public function update(Request $request, GstinValidator $gstin, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['legal_name' => ['required', 'string', 'max:255'], 'trade_name' => ['nullable', 'string', 'max:255'], 'gstin' => ['nullable', 'string', 'size:15'], 'registration_type' => ['required', 'in:regular,composition,unregistered,exempt,SEZ,other'], 'registered_address' => ['nullable', 'string', 'max:5000'], 'state_code' => ['nullable', 'regex:/^[0-9]{2}$/'], 'state_name' => ['nullable', 'string', 'max:80'], 'pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'], 'default_place_of_supply_state_code' => ['nullable', 'regex:/^[0-9]{2}$/'], 'invoice_series' => ['required', 'string', 'max:40'], 'financial_year' => ['nullable', 'regex:/^[0-9]{4}-[0-9]{2}$/'], 'e_invoice_applicable' => ['boolean'], 'e_way_bill_applicable' => ['boolean'], 'aggregate_turnover_band' => ['nullable', 'string', 'max:48'], 'tax_rounding_mode' => ['required', 'in:half_up,truncate'], 'reverse_charge_default' => ['boolean'], 'export_type' => ['required', 'in:domestic,export,sez']]);
        $data['gstin'] = $data['gstin'] ? strtoupper($data['gstin']) : null;
        $data['pan'] = $data['pan'] ? strtoupper($data['pan']) : null;
        if (! $gstin->isStructurallyValid($data['gstin'])) throw ValidationException::withMessages(['gstin' => 'GSTIN format is invalid. This checks structure only; it does not verify registration authenticity.']);
        $settings = GstSetting::firstOrCreate(['company_id' => $request->user()->company_id], ['legal_name' => $data['legal_name']]);
        $settings->update($data);
        $audit->record('compliance.gst.settings_updated', $settings, 'GST business settings updated', ['company_id' => $request->user()->company_id]);

        return back()->with('status', 'GST settings updated. Accountant review is still required before filing.');
    }
}

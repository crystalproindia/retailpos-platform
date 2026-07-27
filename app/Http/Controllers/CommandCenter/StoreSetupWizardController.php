<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Services\Saas\StoreSetupWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StoreSetupWizardController extends Controller
{
    public function show(Request $request, StoreSetupWizardService $wizard): View|RedirectResponse
    {
        abort_unless($wizard->enabled(), 404);
        abort_unless($wizard->canManage($request->user()), 403);
        $record = $wizard->wizard($request->user());
        if ($record->status === 'completed') return redirect()->route('dashboard');
        $displayStep = $request->integer('step');
        $displayStep = $displayStep >= 1 && $displayStep <= $record->current_step ? $displayStep : $record->current_step;
        return view('command-center.onboarding.store-setup.show', ['setup' => $record, 'displayStep' => $displayStep, 'subtypes' => config('store-setup.subtypes.'.$record->industry_key, []), 'flags' => config('store_setup')]);
    }

    public function start(Request $request, StoreSetupWizardService $wizard): RedirectResponse
    {
        $record = $wizard->wizard($request->user());
        $wizard->start($request->user(), $record);
        return redirect()->route('onboarding.store-setup.show');
    }

    public function save(Request $request, StoreSetupWizardService $wizard): RedirectResponse
    {
        $data = $request->validate(['step' => ['required', 'integer', 'between:1,6']]);
        $record = $wizard->wizard($request->user());
        $wizard->save($request->user(), $record, $data['step'], $request->all());
        return redirect()->route('onboarding.store-setup.show')->with('status', 'Your setup progress has been saved.');
    }

    public function skip(Request $request, StoreSetupWizardService $wizard): RedirectResponse
    {
        $record = $wizard->wizard($request->user());
        $wizard->skip($request->user(), $record);
        return redirect()->route('dashboard')->with('status', 'Store setup is saved for later.');
    }

    public function apply(Request $request, StoreSetupWizardService $wizard): RedirectResponse
    {
        $data = $request->validate(['categories' => ['array'], 'categories.*' => ['string', 'max:120'], 'apply_tax' => ['nullable', 'boolean'], 'apply_template' => ['nullable', 'boolean'], 'apply_barcode' => ['nullable', 'boolean']]);
        $record = $wizard->wizard($request->user());
        $wizard->apply($request->user(), $record, $data);
        return redirect()->route('onboarding.store-setup.complete')->with('status', 'Your essential store settings are ready.');
    }

    public function complete(Request $request, StoreSetupWizardService $wizard): View|RedirectResponse
    {
        $record = $wizard->wizard($request->user());
        if ($record->status !== 'completed') return redirect()->route('onboarding.store-setup.show');
        return view('command-center.onboarding.store-setup.complete', ['setup' => $record]);
    }

    public function template(Request $request, StoreSetupWizardService $wizard): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(config('store_setup.product_import_enabled') && $wizard->canManage($request->user()), 403);
        app(\App\Services\AuditLogger::class)->record('saas.store_setup.product_template_downloaded', null, 'Product import template downloaded.', ['company_id' => $request->user()->company_id]);
        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Product name', 'SKU', 'Barcode', 'Category', 'Selling price', 'Purchase price', 'GST rate', 'Opening stock', 'Unit', 'HSN code', 'Reorder level']);
            fputcsv($out, ['Sample product', 'SKU-001', '', 'General Merchandise', '0.00', '0.00', '18', '0', 'piece', '', '0']);
            fclose($out);
        }, 'retailpos-product-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}

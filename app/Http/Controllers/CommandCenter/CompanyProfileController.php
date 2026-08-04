<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyProfileRequest;
use App\Services\Branding\CompanyBrandingService;
use App\Services\AuditLogger;
use App\Services\Saas\IndustryRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function edit(Request $request, IndustryRegistry $industries, CompanyBrandingService $branding): View
    {
        return view('command-center.settings.company-profile', [
            'company' => $request->user()->company,
            'industries' => $industries->enabled(),
            'branding' => $branding->forCompany($request->user()->company),
        ]);
    }

    public function update(UpdateCompanyProfileRequest $request, AuditLogger $audit, IndustryRegistry $industries, CompanyBrandingService $branding): RedirectResponse
    {
        $data = $request->safe()->only(['name', 'industry', 'legal_name', 'address', 'tax_id', 'phone', 'email']);
        $industries->selectable($data['industry']);
        $company = $request->user()->company;
        $company->update($data);
        $audit->record('company.profile.updated', $company, 'Company profile updated.', ['company_id' => $company->id, 'placeholder_company_name' => $company->hasPlaceholderName()]);

        foreach (['company', 'invoice'] as $kind) {
            $fileKey = $kind.'_logo';
            if ($request->hasFile($fileKey)) {
                $company = $branding->replace($company, $request->user(), $request->file($fileKey), $kind);
            } elseif ($request->boolean('remove_'.$fileKey)) {
                $company = $branding->remove($company, $request->user(), $kind);
            }
        }

        return back()->with('status', 'Company profile and branding saved.');
    }
}

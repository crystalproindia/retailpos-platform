<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Saas\IndustryRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function edit(Request $request, IndustryRegistry $industries): View
    {
        return view('command-center.settings.company-profile', [
            'company' => $request->user()->company,
            'industries' => $industries->enabled(),
        ]);
    }

    public function update(Request $request, AuditLogger $audit, IndustryRegistry $industries): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['required', 'string', 'max:80'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:5000'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $industries->selectable($data['industry']);
        $company = $request->user()->company;
        $company->update($data);
        $audit->record('company.profile.updated', $company, 'Company profile updated.', ['company_id' => $company->id, 'placeholder_company_name' => $company->hasPlaceholderName()]);

        return back()->with('status', 'Company profile saved.');
    }
}

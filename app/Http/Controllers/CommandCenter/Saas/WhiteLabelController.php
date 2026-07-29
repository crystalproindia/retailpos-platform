<?php

namespace App\Http\Controllers\CommandCenter\Saas;

use App\Http\Controllers\Controller;
use App\Models\Cms\CmsMedia;
use App\Services\Saas\EntitlementService;
use App\Services\Saas\WhiteLabelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WhiteLabelController extends Controller
{
    public function edit(Request $request, EntitlementService $entitlements, WhiteLabelService $whiteLabel): View
    {
        $this->authorizeAccess($request, $entitlements, 'white-label.view');

        return view('command-center.saas.white-label.edit', [
            'values' => $whiteLabel->values($request->user()->company),
            'media' => CmsMedia::query()->where('company_id', $request->user()->company_id)->whereIn('type', ['image', 'svg'])->orderBy('name')->get(['id', 'name', 'mime_type']),
        ]);
    }

    public function update(Request $request, EntitlementService $entitlements, WhiteLabelService $whiteLabel): RedirectResponse
    {
        $this->authorizeAccess($request, $entitlements, 'white-label.update');
        $companyId = $request->user()->company_id;
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'logo_media_id' => ['nullable', Rule::exists('cms_media', 'id')->where('company_id', $companyId)],
            'favicon_media_id' => ['nullable', Rule::exists('cms_media', 'id')->where('company_id', $companyId)],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'email_sender_name' => ['nullable', 'string', 'max:255'],
            'show_powered_by' => ['nullable', 'boolean'],
            'custom_domain' => ['nullable', 'string', 'max:255'],
            'custom_domain_status' => ['required', Rule::in(['not_configured', 'pending', 'verified', 'active', 'failed'])],
        ]);
        $data['show_powered_by'] = $request->boolean('show_powered_by');

        $whiteLabel->update($request->user()->company, $request->user(), $data);

        return back()->with('status', 'White-label readiness settings saved. Domain routing and SSL remain managed outside this foundation.');
    }

    private function authorizeAccess(Request $request, EntitlementService $entitlements, string $ability): void
    {
        abort_unless($request->user()->can($ability) && $entitlements->allows($request->user()->company, 'white_label'), 403);
    }
}

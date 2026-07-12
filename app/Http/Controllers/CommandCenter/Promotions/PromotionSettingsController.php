<?php

namespace App\Http\Controllers\CommandCenter\Promotions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotions\PromotionSettingsRequest;
use App\Services\AuditLogger;
use App\Services\Promotions\PromotionSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionSettingsController extends Controller
{
    public function index(Request $request, PromotionSettingsService $service): View { return view('command-center.promotions.settings.index', ['settings' => $service->settings($request->user()->company_id)]); }
    public function update(PromotionSettingsRequest $request, PromotionSettingsService $service, AuditLogger $auditLogger): RedirectResponse
    {
        $settings = $service->settings($request->user()->company_id); $data = $request->validated();
        foreach (['allow_stacking', 'allow_coupon_with_auto_discount', 'require_approval_for_promotions', 'show_discount_breakup_on_bill_future'] as $key) $data[$key] = (bool) ($data[$key] ?? false);
        $settings->update($data); $auditLogger->record('promotion.settings.updated', $settings, 'Promotion settings updated');
        return back()->with('status', 'Promotion settings updated.');
    }
}

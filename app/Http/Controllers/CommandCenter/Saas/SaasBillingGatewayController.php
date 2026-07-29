<?php

namespace App\Http\Controllers\CommandCenter\Saas;

use App\Http\Controllers\Controller;
use App\Services\Saas\SaasGatewaySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaasBillingGatewayController extends Controller
{
    public function index(SaasGatewaySettingsService $settings): View { $connection = $settings->forPlatform(); return view('command-center.saas.billing.gateway', ['connection' => $connection, 'maskedKeyId' => $settings->maskedKeyId($connection), 'test' => $settings->testConnection()]); }
    public function update(Request $request, SaasGatewaySettingsService $settings): RedirectResponse { $data = $request->validate(['key_id' => ['required', 'string', 'max:120'], 'key_secret' => ['nullable', 'string', 'max:500'], 'webhook_secret' => ['nullable', 'string', 'max:500'], 'account_email' => ['nullable', 'email', 'max:255'], 'mode' => ['required', 'in:test'], 'enabled' => ['nullable', 'boolean']]); $settings->save($request->user(), $data + ['enabled' => $request->boolean('enabled')]); return back()->with('status', 'Razorpay test-mode settings saved.'); }
    public function test(SaasGatewaySettingsService $settings): RedirectResponse { return back()->with($settings->testConnection()['configured'] ? 'status' : 'error', $settings->testConnection()['message']); }
}

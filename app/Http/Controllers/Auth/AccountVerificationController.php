<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Saas\AccountVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountVerificationController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->verification_status === 'verified') return redirect()->route('dashboard');
        return view('auth.account-verification', ['user' => $request->user()]);
    }

    public function verify(Request $request, AccountVerificationService $verification): RedirectResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(['email', 'mobile'])], 'code' => ['required', 'digits:6']]);
        $verification->verify($request->user(), $data['channel'], $data['code']);
        return redirect()->route('dashboard')->with('status', 'Your account has been verified.');
    }

    public function resend(Request $request, AccountVerificationService $verification): RedirectResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(['email', 'mobile'])]]);
        if ($data['channel'] === 'mobile') return back()->withErrors(['channel' => 'Mobile OTP delivery needs an SMS provider before it can be enabled.']);
        $verification->issue($request->user(), $data['channel']);
        return back()->with('status', 'A new verification code has been prepared for the configured email delivery service.');
    }
}

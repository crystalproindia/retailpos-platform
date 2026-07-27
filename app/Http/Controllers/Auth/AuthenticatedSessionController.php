<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Saas\StoreSetupWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, StoreSetupWizardService $storeSetup): RedirectResponse
    {
        $request->authenticate();

        if ($request->user()?->verification_status === 'pending') {
            return redirect()->route('account.verification.show');
        }

        if ($request->user() && $storeSetup->shouldRedirect($request->user())) {
            return redirect()->route('onboarding.store-setup.show');
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

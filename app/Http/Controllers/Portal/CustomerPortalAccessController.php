<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\CustomerPortalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerPortalAccessController extends Controller
{
    public function login(): View
    {
        return view('portal.login');
    }

    public function access(Request $request, CustomerPortalAccessService $access, string $token): RedirectResponse
    {
        $portalUser = $access->consume($token);
        if (! $portalUser) return redirect()->route('portal.login')->with('error', 'This secure access link is invalid, expired, or has already been used.');

        $request->session()->regenerate();
        $request->session()->put('customer_portal_user_id', $portalUser->id);

        return redirect()->route('portal.dashboard')->with('status', 'Welcome back, '.$portalUser->name.'.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('customer_portal_user_id');
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')->with('status', 'You have been signed out.');
    }
}

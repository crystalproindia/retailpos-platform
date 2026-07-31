<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkforceAccountIsActive
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $inactiveStatuses = ['pending_invitation', 'suspended', 'disabled'];

        // Treat legacy and pre-migration account rows as active unless their state
        // explicitly prevents access. The migration backfills `active` for production.
        if (! $user || ($user->is_active && ! in_array($user->account_status, $inactiveStatuses, true))) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => 'This account is not active. Contact a company administrator for help.']);
    }
}

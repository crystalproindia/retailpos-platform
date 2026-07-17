<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfCustomerPortalAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('customer_portal_user_id')) {
            return redirect()->route('portal.dashboard');
        }

        return $next($request);
    }
}

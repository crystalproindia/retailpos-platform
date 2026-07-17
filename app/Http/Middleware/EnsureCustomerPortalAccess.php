<?php

namespace App\Http\Middleware;

use App\Models\Crm\CrmCustomerPortalUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerPortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $portalUser = CrmCustomerPortalUser::query()
            ->with('customer')
            ->whereKey($request->session()->get('customer_portal_user_id'))
            ->where('status', 'active')
            ->first();

        if (! $portalUser) {
            $request->session()->forget('customer_portal_user_id');

            return redirect()->route('portal.login')->with('error', 'Your portal session has ended. Please use a current secure access link.');
        }

        $request->attributes->set('portalUser', $portalUser);

        return $next($request);
    }
}

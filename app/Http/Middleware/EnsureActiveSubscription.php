<?php

namespace App\Http\Middleware;

use App\Services\Saas\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('saas.enforcement_enabled', false)) {
            return $next($request);
        }

        $company = $request->user()?->company;
        abort_unless($company && app(EntitlementService::class)->active($company), Response::HTTP_FORBIDDEN, 'Your account is not currently active.');

        return $next($request);
    }
}

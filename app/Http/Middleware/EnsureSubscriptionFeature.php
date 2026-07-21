<?php

namespace App\Http\Middleware;

use App\Services\Saas\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionFeature
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! config('saas.enforcement_enabled', false)) {
            return $next($request);
        }

        $company = $request->user()?->company;
        abort_unless($company && app(EntitlementService::class)->allows($company, $feature), Response::HTTP_FORBIDDEN, 'Your subscription does not include this feature.');

        return $next($request);
    }
}

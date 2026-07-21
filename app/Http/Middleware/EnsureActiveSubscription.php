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
        $user = $request->user();
        if (! $user || ! config('saas.enforcement_enabled', false) || $user->is_platform_admin) {
            return $next($request);
        }

        if ($request->routeIs('account.subscription.*', 'logout')) {
            return $next($request);
        }

        $company = $user->company;
        abort_unless($company && app(EntitlementService::class)->active($company), Response::HTTP_FORBIDDEN, 'Your account is not currently active.');

        if ($feature = $this->featureFor($request)) {
            abort_unless(app(EntitlementService::class)->allows($company, $feature), Response::HTTP_FORBIDDEN, 'Your subscription does not include this feature.');
        }

        return $next($request);
    }

    private function featureFor(Request $request): ?string
    {
        $name = $request->route()?->getName() ?? '';

        return match (true) {
            str_starts_with($name, 'crm.quotations.') => 'quotations',
            str_starts_with($name, 'crm.invoices.'), str_starts_with($name, 'sales.invoices.') => 'sales_invoices',
            str_starts_with($name, 'crm.reports.'), str_starts_with($name, 'reports.') => 'reports',
            str_starts_with($name, 'crm.') => 'crm',
            str_starts_with($name, 'pos.') => 'pos',
            str_starts_with($name, 'inventory.') => 'inventory',
            str_starts_with($name, 'purchases.'), str_starts_with($name, 'purchase-'), str_starts_with($name, 'supplier-'), str_starts_with($name, 'input-gst-') => 'purchases',
            str_starts_with($name, 'compliance.') => 'gst_compliance',
            str_starts_with($name, 'cms.'), str_starts_with($name, 'website.') => 'cms',
            str_starts_with($name, 'settings.integrations.email'), str_starts_with($name, 'settings.email-deliveries') => 'email_integration',
            default => null,
        };
    }
}

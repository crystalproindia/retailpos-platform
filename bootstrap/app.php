<?php

use App\Http\Middleware\EnsureActiveSubscription;
use App\Http\Middleware\EnsureCustomerPortalAccess;
use App\Http\Middleware\EnsurePlatformAdministrator;
use App\Http\Middleware\EnsurePublicLeadToken;
use App\Http\Middleware\EnsureSubscriptionFeature;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureWorkforceAccountIsActive;
use App\Http\Middleware\RedirectIfCustomerPortalAuthenticated;
use App\Http\Middleware\RejectOversizedPublicLeadPayload;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', EnsureActiveSubscription::class);
        $middleware->appendToGroup('api', EnsureActiveSubscription::class);
        $middleware->alias([
            'platform-admin' => EnsurePlatformAdministrator::class,
            'subscription.active' => EnsureActiveSubscription::class,
            'subscription.feature' => EnsureSubscriptionFeature::class,
            'workforce.account.active' => EnsureWorkforceAccountIsActive::class,
            'role' => EnsureUserHasRole::class,
            'public.lead.token' => EnsurePublicLeadToken::class,
            'public.lead.payload' => RejectOversizedPublicLeadPayload::class,
            'portal.auth' => EnsureCustomerPortalAccess::class,
            'portal.guest' => RedirectIfCustomerPortalAuthenticated::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('sales/invoices/customers*'),
        );
    })->create();

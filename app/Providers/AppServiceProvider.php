<?php

namespace App\Providers;

use App\Contracts\Crm\SalesMessageGeneratorInterface;
use App\Enums\UserRole;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmCompany;
use App\Models\Crm\CrmContact;
use App\Models\Crm\CrmLead;
use App\Models\User;
use App\Policies\Crm\CrmActivityPolicy;
use App\Policies\Crm\CrmCompanyPolicy;
use App\Policies\Crm\CrmContactPolicy;
use App\Policies\Crm\CrmLeadPolicy;
use App\Services\AuditLogger;
use App\Services\Crm\TemplateSalesMessageGenerator;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SalesMessageGeneratorInterface::class, TemplateSalesMessageGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('public-leads', fn ($request) => Limit::perMinute((int) config('services.retailpos.public_lead_rate_limit', 30))
            ->by('public-leads:'.$request->ip()));

        RateLimiter::for('public-cms', fn ($request) => Limit::perMinute(120)
            ->by('public-cms:'.$request->ip()));

        RateLimiter::for('portal-access', fn ($request) => Limit::perMinute(10)
            ->by('portal-access:'.$request->ip()));

        RateLimiter::for('portal-support', fn ($request) => Limit::perMinute(5)
            ->by('portal-support:'.($request->session()->get('customer_portal_user_id') ?? $request->ip())));

        RateLimiter::for('portal-service-requests', fn ($request) => Limit::perMinute(3)
            ->by('portal-service-requests:'.($request->session()->get('customer_portal_user_id') ?? $request->ip())));

        RateLimiter::for('email-test', fn ($request) => Limit::perMinute(3)
            ->by('email-test:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('public-quotation', fn ($request) => Limit::perMinute(30)
            ->by('public-quotation:'.$request->ip()));

        Gate::policy(CrmLead::class, CrmLeadPolicy::class);
        Gate::policy(CrmCompany::class, CrmCompanyPolicy::class);
        Gate::policy(CrmContact::class, CrmContactPolicy::class);
        Gate::policy(CrmActivity::class, CrmActivityPolicy::class);

        collect(config('permissions.capabilities', []))->each(function (array $roles, string $capability): void {
            Gate::define($capability, function (User $user) use ($roles): bool {
                $role = $user->role instanceof UserRole ? $user->role->value : $user->role;

                return in_array($role, $roles, true);
            });
        });

        Event::listen(Login::class, function (Login $event): void {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

            app(AuditLogger::class)->record('auth.login', $event->user, 'User logged in');
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if ($event->user) {
                app(AuditLogger::class)->record('auth.logout', $event->user, 'User logged out');
            }
        });
    }
}

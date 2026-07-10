<?php

namespace App\Providers;

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
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

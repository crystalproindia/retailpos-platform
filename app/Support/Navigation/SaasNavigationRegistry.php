<?php

namespace App\Support\Navigation;

use App\Models\User;

class SaasNavigationRegistry
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function platformItems(?User $user): array
    {
        if (! $user?->is_platform_admin) {
            return [];
        }

        return $this->filterForUser($user, [
            ['label' => 'SaaS Dashboard', 'route' => 'saas.dashboard', 'icon' => 'dashboard', 'permission' => 'saas.dashboard.view', 'active' => ['saas.dashboard']],
            ['label' => 'Tenants & Subscriptions', 'route' => 'saas.subscriptions.index', 'icon' => 'company', 'permission' => 'saas.subscriptions.view', 'active' => ['saas.subscriptions.*', 'saas.tenants.*']],
            ['label' => 'Plans', 'route' => 'saas.plans.index', 'icon' => 'layers', 'permission' => 'saas.plans.view', 'active' => ['saas.plans.*']],
            ['label' => 'Onboarding', 'route' => 'saas.onboarding.index', 'icon' => 'users', 'permission' => 'saas.onboarding.manage', 'active' => ['saas.onboarding.*']],
            ['label' => 'Resellers', 'route' => 'saas.resellers.index', 'icon' => 'users', 'permission' => 'saas.resellers.view', 'active' => ['saas.resellers.*']],
            ...$this->platformBillingItems($user),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function platformBillingItems(?User $user): array
    {
        if (! $user?->is_platform_admin) {
            return [];
        }

        return $this->filterForUser($user, [
            ['label' => 'Billing Dashboard', 'route' => 'saas.billing.index', 'icon' => 'dashboard', 'permission' => 'saas.billing.view', 'active' => ['saas.billing.index']],
            ['label' => 'Subscription Invoices', 'route' => 'saas.billing.invoices.index', 'icon' => 'audit', 'permission' => 'saas.billing.view', 'active' => ['saas.billing.invoices.*', 'saas.billing.show']],
            ['label' => 'Payments', 'route' => 'saas.billing.payments.index', 'icon' => 'sales', 'permission' => 'saas.billing.view', 'active' => ['saas.billing.payments.*']],
            ['label' => 'Refunds', 'route' => 'saas.billing.refunds.index', 'icon' => 'returns', 'permission' => 'saas.billing.refund', 'active' => ['saas.billing.refunds.*']],
            ['label' => 'Reconciliation', 'route' => 'saas.billing.reconciliation.index', 'icon' => 'activity', 'permission' => 'saas.billing.reconcile', 'active' => ['saas.billing.reconciliation.*']],
            ['label' => 'Gateway Settings', 'route' => 'saas.billing.gateway.index', 'icon' => 'settings', 'permission' => 'saas.billing.gateway.manage', 'active' => ['saas.billing.gateway.*']],
            ['label' => 'Billing Reports', 'route' => 'saas.billing.reports', 'icon' => 'analytics', 'permission' => 'saas.billing.view', 'active' => ['saas.billing.reports']],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tenantSubscriptionItems(?User $user): array
    {
        if (! $user?->isAdministrator() || $user->is_platform_admin) {
            return [];
        }

        return $this->filterForUser($user, [
            ['label' => 'Current Plan & Usage', 'route' => 'account.subscription.index', 'icon' => 'credit-card', 'permission' => 'subscription.view', 'active' => ['account.subscription.index']],
            ['label' => 'Billing', 'route' => 'account.subscription.billing.index', 'icon' => 'audit', 'permission' => 'subscription.billing.view', 'active' => ['account.subscription.billing.*']],
        ]);
    }

    /**
     * @param array<string, mixed> $item
     */
    public function url(array $item): string
    {
        return route($item['route'], $item['route_params'] ?? []);
    }

    /**
     * @param array<string, mixed> $item
     */
    public function isActive(array $item): bool
    {
        return request()->routeIs(...($item['active'] ?? [$item['route']]));
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function filterForUser(User $user, array $items): array
    {
        return array_values(array_filter($items, fn (array $item): bool => ! isset($item['permission']) || $user->can($item['permission'])));
    }
}

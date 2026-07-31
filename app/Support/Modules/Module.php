<?php

namespace App\Support\Modules;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Saas\EntitlementService;

class Module
{
    /**
     * @param  array<string, mixed>  $routeParameters
     * @param  array<int, string>  $roles
     * @param  array<string, mixed>|null  $badge
     * @param  array<int, Module>  $children
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly string $icon,
        public readonly string $route,
        public readonly array $routeParameters,
        public readonly int $sortOrder,
        public readonly string $category,
        public readonly bool $enabled,
        public readonly bool $visibleInSidebar,
        public readonly array $roles,
        public readonly ?array $badge = null,
        public readonly ?string $licenseKey = null,
        public readonly ?string $parentId = null,
        public readonly array $children = [],
        public readonly ?string $permission = null,
        public readonly array $searchAliases = [],
        public readonly bool $searchable = true,
        public readonly bool $navigable = true,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(string $id, array $attributes): self
    {
        return new self(
            id: $id,
            name: $attributes['name'],
            description: $attributes['description'] ?? '',
            icon: $attributes['icon'] ?? 'module',
            route: $attributes['route'],
            routeParameters: $attributes['route_params'] ?? [],
            sortOrder: (int) ($attributes['sort_order'] ?? 0),
            category: $attributes['category'] ?? 'General',
            enabled: (bool) ($attributes['enabled'] ?? true),
            visibleInSidebar: (bool) ($attributes['visible_in_sidebar'] ?? true),
            roles: array_values($attributes['roles'] ?? []),
            badge: $attributes['badge'] ?? null,
            licenseKey: $attributes['license_key'] ?? null,
            parentId: $attributes['parent_id'] ?? null,
            permission: $attributes['permission'] ?? null,
            searchAliases: array_values($attributes['search_aliases'] ?? []),
            searchable: (bool) ($attributes['searchable'] ?? true),
        );
    }

    public function allowedFor(UserRole|string|null $role): bool
    {
        if ($role === null) {
            return false;
        }

        $roleValue = $role instanceof UserRole ? $role->value : $role;

        return in_array($roleValue, $this->roles, true);
    }

    public function allowedForUser(User $user): bool
    {
        if (! $this->allowedFor($user->role) || ($this->permission && ! $user->can($this->permission))) {
            return false;
        }

        if (! config('saas.enforcement_enabled', false) || ! $this->licenseKey || ! $user->company) {
            return true;
        }

        return app(EntitlementService::class)->allows($user->company, $this->licenseKey);
    }

    public function url(): string
    {
        return route($this->route, $this->routeParameters);
    }

    public function navigationIdentity(): string
    {
        return 'module:'.$this->id;
    }

    public function isActive(): bool
    {
        if ($this->route === 'settings.show') {
            return request()->routeIs('settings.*')
                || request()->routeIs('sales.invoices.templates.*');
        }

        if ($this->route === 'sales.invoices.templates.index') {
            return request()->routeIs('sales.invoices.templates.*');
        }

        if (str_starts_with($this->route, 'cms.')) {
            return $this->familyRouteIsActive('cms');
        }

        if (str_starts_with($this->route, 'website.')) {
            return $this->familyRouteIsActive('website');
        }

        if (str_starts_with($this->route, 'crm.')) {
            return $this->familyRouteIsActive('crm');
        }

        if (str_starts_with($this->route, 'sales.')) {
            return $this->familyRouteIsActive('sales');
        }

        if (str_starts_with($this->route, 'pos.')) {
            return $this->familyRouteIsActive('pos');
        }

        if (str_starts_with($this->route, 'customers.')) {
            return $this->familyRouteIsActive('customers');
        }

        if (str_starts_with($this->route, 'notifications.')) {
            return $this->familyRouteIsActive('notifications');
        }

        if (str_starts_with($this->route, 'operations.')) {
            return $this->familyRouteIsActive('operations');
        }

        if (str_starts_with($this->route, 'compliance.')) {
            return $this->familyRouteIsActive('compliance');
        }

        if (str_starts_with($this->route, 'inventory.')) {
            return $this->familyRouteIsActive('inventory');
        }

        if (str_starts_with($this->route, 'purchases.')) {
            return $this->familyRouteIsActive('purchases');
        }

        if (str_starts_with($this->route, 'promotions.')) {
            return $this->familyRouteIsActive('promotions');
        }

        if (str_starts_with($this->route, 'workforce.')) {
            return $this->familyRouteIsActive('workforce');
        }

        return request()->routeIs($this->route)
            && collect($this->routeParameters)
                ->every(fn (mixed $value, string $key): bool => request()->route($key) === $value);
    }

    private function familyRouteIsActive(string $family): bool
    {
        if ($this->parentId === null) {
            return request()->routeIs($family.'.*');
        }

        if (str_ends_with($this->route, '.index')) {
            return request()->routeIs(substr($this->route, 0, -strlen('.index')).'.*');
        }

        return request()->routeIs($this->route);
    }

    /**
     * @param  array<int, Module>  $children
     */
    public function withChildren(array $children, ?bool $navigable = null): self
    {
        return new self(
            id: $this->id,
            name: $this->name,
            description: $this->description,
            icon: $this->icon,
            route: $this->route,
            routeParameters: $this->routeParameters,
            sortOrder: $this->sortOrder,
            category: $this->category,
            enabled: $this->enabled,
            visibleInSidebar: $this->visibleInSidebar,
            roles: $this->roles,
            badge: $this->badge,
            licenseKey: $this->licenseKey,
            parentId: $this->parentId,
            children: $children,
            permission: $this->permission,
            searchAliases: $this->searchAliases,
            searchable: $this->searchable,
            navigable: $navigable ?? $this->navigable,
        );
    }
}

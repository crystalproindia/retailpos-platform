<?php

namespace App\Support\Modules;

use App\Enums\UserRole;

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

    public function url(): string
    {
        return route($this->route, $this->routeParameters);
    }

    public function isActive(): bool
    {
        if ($this->route === 'settings.show') {
            return request()->routeIs('settings.*');
        }

        if (str_starts_with($this->route, 'cms.')) {
            return request()->routeIs('cms.*');
        }

        if (str_starts_with($this->route, 'crm.')) {
            return request()->routeIs('crm.*');
        }

        if (str_starts_with($this->route, 'notifications.')) {
            return request()->routeIs('notifications.*');
        }

        if (str_starts_with($this->route, 'operations.')) {
            return request()->routeIs('operations.*');
        }

        if (str_starts_with($this->route, 'inventory.')) {
            return request()->routeIs('inventory.*');
        }

        return request()->routeIs($this->route)
            && collect($this->routeParameters)
                ->every(fn (mixed $value, string $key): bool => request()->route($key) === $value);
    }

    /**
     * @param  array<int, Module>  $children
     */
    public function withChildren(array $children): self
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
        );
    }
}

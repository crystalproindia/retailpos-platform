<?php

namespace App\Support\Modules;

use App\Enums\UserRole;
use Illuminate\Support\Collection;

class ModuleRegistry
{
    /**
     * @var Collection<string, Module>|null
     */
    private ?Collection $modules = null;

    /**
     * @return Collection<string, Module>
     */
    public function all(): Collection
    {
        return $this->modules ??= $this->loadModules();
    }

    /**
     * @return Collection<string, Module>
     */
    public function enabled(): Collection
    {
        return $this->all()
            ->filter(fn (Module $module): bool => $module->enabled)
            ->sortBy('sortOrder');
    }

    public function find(string $id): ?Module
    {
        return $this->all()->get($id);
    }

    /**
     * @return Collection<string, Collection<int, Module>>
     */
    public function grouped(?UserRole $role = null): Collection
    {
        return $this->sidebar($role)
            ->groupBy('category')
            ->map(fn (Collection $modules): Collection => $modules->values());
    }

    /**
     * @return Collection<int, Module>
     */
    public function sidebar(?UserRole $role = null): Collection
    {
        $modules = $this->enabled()
            ->filter(fn (Module $module): bool => $module->visibleInSidebar)
            ->when($role, fn (Collection $modules): Collection => $modules->filter(
                fn (Module $module): bool => $module->allowedFor($role),
            ))
            ->values();

        return $this->withChildren($modules);
    }

    /**
     * @return Collection<int, Module>
     */
    public function forRole(UserRole|string $role): Collection
    {
        return $this->enabled()
            ->filter(fn (Module $module): bool => $module->allowedFor($role))
            ->values();
    }

    /**
     * @return Collection<string, Module>
     */
    private function loadModules(): Collection
    {
        return collect(config('modules.modules', []))
            ->map(fn (array $attributes, string $id): Module => Module::fromArray($id, $attributes))
            ->sortBy('sortOrder');
    }

    /**
     * @param  Collection<int, Module>  $modules
     * @return Collection<int, Module>
     */
    private function withChildren(Collection $modules): Collection
    {
        $children = $modules
            ->filter(fn (Module $module): bool => $module->parentId !== null)
            ->groupBy('parentId');

        return $modules
            ->filter(fn (Module $module): bool => $module->parentId === null)
            ->map(fn (Module $module): Module => $module->withChildren(
                ($children->get($module->id) ?? collect())->values()->all(),
            ))
            ->values();
    }
}

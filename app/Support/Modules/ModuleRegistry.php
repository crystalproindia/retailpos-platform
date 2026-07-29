<?php

namespace App\Support\Modules;

use App\Enums\UserRole;
use App\Models\User;
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
        $visible = $this->visibleEnabled();
        $authorized = $role
            ? $visible->filter(fn (Module $module): bool => $module->allowedFor($role))
            : $visible;

        return $this->navigationTree($visible, $authorized);
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

    /** @return Collection<int, Module> */
    public function sidebarForUser(User $user): Collection
    {
        $visible = $this->visibleEnabled();
        $authorized = $visible->filter(fn (Module $module): bool => $module->allowedForUser($user));

        return $this->navigationTree($visible, $authorized);
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

    /** @return Collection<string, Module> */
    private function visibleEnabled(): Collection
    {
        return $this->enabled()
            ->filter(fn (Module $module): bool => $module->visibleInSidebar);
    }

    /**
     * @param  Collection<string, Module>  $visible
     * @param  Collection<string, Module>  $authorized
     * @return Collection<int, Module>
     */
    private function navigationTree(Collection $visible, Collection $authorized): Collection
    {
        $selected = clone $authorized;

        foreach ($authorized as $module) {
            $parentId = $module->parentId;
            $seen = [];

            while ($parentId !== null && ! isset($seen[$parentId])) {
                $seen[$parentId] = true;
                $parent = $visible->get($parentId);
                if (! $parent) break;

                $selected->put($parent->id, $parent);
                $parentId = $parent->parentId;
            }
        }

        return $this->withChildren(
            $selected->sortBy('sortOrder'),
            $authorized->mapWithKeys(fn (Module $module): array => [$module->id => true]),
        );
    }

    /**
     * @param  Collection<string, Module>  $modules
     * @param  Collection<string, string>|null  $navigableIds
     * @return Collection<int, Module>
     */
    private function withChildren(Collection $modules, ?Collection $navigableIds = null): Collection
    {
        $children = $modules
            ->filter(fn (Module $module): bool => $module->parentId !== null)
            ->groupBy('parentId');

        return $modules
            ->filter(fn (Module $module): bool => $module->parentId === null)
            ->map(fn (Module $module): Module => $this->buildModule($module, $children, $navigableIds))
            ->values();
    }

    /**
     * @param  Collection<string, Collection<int, Module>>  $children
     * @param  Collection<string, string>|null  $navigableIds
     */
    private function buildModule(Module $module, Collection $children, ?Collection $navigableIds): Module
    {
        return $module->withChildren(
            ($children->get($module->id) ?? collect())
                ->map(fn (Module $child): Module => $this->buildModule($child, $children, $navigableIds))
                ->values()
                ->all(),
            $navigableIds?->has($module->id) ?? true,
        );
    }
}

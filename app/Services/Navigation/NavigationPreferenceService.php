<?php

namespace App\Services\Navigation;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserNavigationPreference;
use App\Support\Modules\Module;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class NavigationPreferenceService
{
    /** @var array<string, UserNavigationPreference> */
    private array $preferenceCache = [];

    public function __construct(private readonly ModuleRegistry $modules) {}

    public function preferenceFor(User $user): UserNavigationPreference
    {
        $key = $user->company_id.':'.$user->id;

        return $this->preferenceCache[$key] ??= UserNavigationPreference::query()->firstOrCreate(
            ['company_id' => $user->company_id, 'user_id' => $user->id],
            ['hidden_module_ids' => [], 'pinned_module_ids' => [], 'module_order' => []],
        );
    }

    /** @return Collection<int, Module> */
    public function authorizedModules(User $user): Collection
    {
        return $this->flatten($this->modules->sidebarForUser($user))
            ->filter(fn (Module $module): bool => $module->navigable)
            ->unique('id')
            ->values();
    }

    /** @return Collection<int, Module> */
    public function visibleModules(User $user): Collection
    {
        $preference = $this->preferenceFor($user);
        $hidden = $this->validIds($preference->hidden_module_ids, $this->authorizedModules($user));

        return $this->ordered(
            $this->authorizedModules($user)->reject(fn (Module $module): bool => in_array($module->id, $hidden, true)),
            $preference->module_order,
        );
    }

    /** @return Collection<int, Module> */
    public function pinnedModules(User $user): Collection
    {
        $preference = $this->preferenceFor($user);
        $visible = $this->visibleModules($user);
        $pinned = $this->validIds($preference->pinned_module_ids, $visible);

        return $this->ordered($visible->filter(fn (Module $module): bool => in_array($module->id, $pinned, true)), $pinned);
    }

    /** @return Collection<int, Module> */
    public function hiddenModules(User $user): Collection
    {
        $preference = $this->preferenceFor($user);
        $authorized = $this->authorizedModules($user);
        $hidden = $this->validIds($preference->hidden_module_ids, $authorized);

        return $this->ordered($authorized->filter(fn (Module $module): bool => in_array($module->id, $hidden, true)), $preference->module_order);
    }

    /** @return Collection<int, Module> */
    public function sidebarForUser(User $user): Collection
    {
        $preference = $this->preferenceFor($user);
        $hidden = $this->validIds($preference->hidden_module_ids, $this->authorizedModules($user));

        return $this->orderTree(
            $this->filterTree($this->modules->sidebarForUser($user), $hidden),
            $preference->module_order,
        );
    }

    /** @return array<string, array<int, string>> */
    public function presets(): array
    {
        return config('navigation.presets', []);
    }

    /** @param array<string, mixed> $input */
    public function save(User $user, array $input): UserNavigationPreference
    {
        $preference = $this->preferenceFor($user);
        $authorized = $this->authorizedModules($user);
        $visible = $this->validIds($input['visible_module_ids'] ?? [], $authorized);
        $pinned = $this->validIds($input['pinned_module_ids'] ?? [], $authorized);
        $order = $this->validIds($input['module_order'] ?? [], $authorized);

        $preference->update([
            'hidden_module_ids' => $authorized->pluck('id')->reject(fn (string $id): bool => in_array($id, $visible, true))->values()->all(),
            'pinned_module_ids' => array_values(array_intersect($pinned, $visible)),
            'module_order' => $order,
            'selected_preset' => null,
        ]);

        return $this->preferenceCache[$user->company_id.':'.$user->id] = $preference->refresh();
    }

    public function applyPreset(User $user, string $preset): UserNavigationPreference
    {
        $definition = $this->presets()[$preset] ?? null;
        abort_unless(is_array($definition), 422);

        $authorized = $this->authorizedModules($user);
        $visible = $this->validIds($definition['visible'] ?? [], $authorized);
        if ($visible === [] && ($definition['visible'] ?? []) === []) {
            $visible = $authorized->pluck('id')->all();
        }
        $pinned = $this->validIds($definition['pinned'] ?? [], $authorized);

        $preference = $this->preferenceFor($user);
        $preference->update([
            'hidden_module_ids' => $authorized->pluck('id')->reject(fn (string $id): bool => in_array($id, $visible, true))->values()->all(),
            'pinned_module_ids' => array_values(array_intersect($pinned, $visible)),
            'module_order' => $visible,
            'selected_preset' => $preset,
        ]);

        return $this->preferenceCache[$user->company_id.':'.$user->id] = $preference->refresh();
    }

    public function reset(User $user): UserNavigationPreference
    {
        return $this->applyPreset($user, $this->defaultPresetFor($user));
    }

    /** @return Collection<int, array{label:string,description:string,icon:string,url:string,tone:string,module_id:string}> */
    public function quickActions(User $user): Collection
    {
        $available = $this->authorizedModules($user)->keyBy('id');
        $actions = [
            ['module' => 'sales', 'label' => 'Create invoice', 'description' => 'Start a customer bill', 'route' => 'sales.invoices.create', 'icon' => 'sales', 'tone' => 'indigo'],
            ['module' => 'pos', 'label' => 'Open POS', 'description' => 'Start fast billing', 'route' => 'pos.index', 'icon' => 'pos', 'tone' => 'emerald'],
            ['module' => 'customers', 'label' => 'Add customer', 'description' => 'Create a customer record', 'route' => 'customers.create', 'icon' => 'customers', 'tone' => 'amber'],
            ['module' => 'products', 'label' => 'Add product', 'description' => 'Create an inventory item', 'route' => 'inventory.products.create', 'icon' => 'products', 'tone' => 'emerald'],
            ['module' => 'purchase-orders', 'label' => 'Create purchase order', 'description' => 'Prepare a supplier order', 'route' => 'purchases.orders.create', 'icon' => 'purchases', 'tone' => 'violet'],
            ['module' => 'outlet-transfer', 'label' => 'Stock transfer', 'description' => 'Move stock between outlets', 'route' => 'inventory.transfers.create', 'icon' => 'transfer', 'tone' => 'emerald'],
            ['module' => 'leads', 'label' => 'Create lead', 'description' => 'Capture a new enquiry', 'route' => 'crm.leads.create', 'icon' => 'leads', 'tone' => 'amber'],
            ['module' => 'reports', 'label' => 'Open reports', 'description' => 'Review live performance', 'route' => 'reports.index', 'icon' => 'reports', 'tone' => 'cyan'],
            ['module' => 'workforce-employees', 'label' => 'Add employee', 'description' => 'Manage your workforce', 'route' => 'workforce.employees.create', 'icon' => 'employees', 'tone' => 'rose'],
        ];

        return collect($actions)
            ->filter(fn (array $action): bool => $available->has($action['module']) && Route::has($action['route']))
            ->map(fn (array $action): array => [...$action, 'url' => route($action['route']), 'module_id' => $action['module']])
            ->values();
    }

    /** @param Collection<int, Module> $modules @param array<int, mixed>|null $requested */
    private function validIds(?array $requested, Collection $modules): array
    {
        $allowed = $modules->pluck('id')->flip();

        return collect($requested ?? [])
            ->filter(fn (mixed $id): bool => is_string($id))
            ->unique()
            ->filter(fn (string $id): bool => $allowed->has($id))
            ->values()
            ->all();
    }

    /** @param Collection<int, Module> $modules @param array<int, mixed>|null $order */
    private function ordered(Collection $modules, ?array $order): Collection
    {
        $positions = collect($order ?? [])->flip();

        return $modules->sortBy(fn (Module $module): array => [
            $positions->get($module->id, PHP_INT_MAX),
            $module->sortOrder,
            $module->name,
        ])->values();
    }

    private function defaultPresetFor(User $user): string
    {
        return match ($user->role) {
            UserRole::Administrator => 'full_admin',
            UserRole::Manager => 'manager',
            UserRole::Sales => 'sales',
            UserRole::Staff => 'retail_cashier',
        };
    }

    /** @param Collection<int, Module> $modules @return Collection<int, Module> */
    private function flatten(Collection $modules): Collection
    {
        return $modules->flatMap(function (Module $module): array {
            return [$module, ...$this->flatten(collect($module->children))->all()];
        });
    }

    /** @param Collection<int, Module> $modules @param array<int, string> $hidden @return Collection<int, Module> */
    private function filterTree(Collection $modules, array $hidden): Collection
    {
        return $modules->map(function (Module $module) use ($hidden): ?Module {
            $children = $this->filterTree(collect($module->children), $hidden);
            $isHidden = in_array($module->id, $hidden, true);

            if ($isHidden && $children->isEmpty()) {
                return null;
            }

            return $module->withChildren($children->all(), $module->navigable && ! $isHidden);
        })->filter()->values();
    }

    /** @param Collection<int, Module> $modules @param array<int, mixed>|null $order @return Collection<int, Module> */
    private function orderTree(Collection $modules, ?array $order): Collection
    {
        return $this->ordered($modules, $order)
            ->map(fn (Module $module): Module => $module->withChildren(
                $this->orderTree(collect($module->children), $order)->all(),
                $module->navigable,
            ))
            ->values();
    }
}

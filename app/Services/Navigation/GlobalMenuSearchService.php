<?php

namespace App\Services\Navigation;

use App\Models\User;
use App\Support\Modules\Module;
use App\Support\Modules\ModuleRegistry;
use App\Support\Navigation\SaasNavigationRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

class GlobalMenuSearchService
{
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly SaasNavigationRegistry $saasNavigation,
    ) {}

    /** @return Collection<int, array{navigation_key:string,label:string,route:string,url:string,icon:string,breadcrumb:string,group:string,aliases:array<int,string>}> */
    public function entriesFor(User $user): Collection
    {
        $entries = collect();

        foreach ($this->modules->sidebarForUser($user) as $module) {
            foreach ($this->searchableModules($module) as [$item, $parentName]) {
                $entries->push($this->moduleEntry($item, $parentName));
            }
        }

        foreach ([...$this->saasNavigation->platformItems($user), ...$this->saasNavigation->tenantSubscriptionItems($user)] as $item) {
            if (! Route::has($item['route'])) continue;
            $entries->push([
                'navigation_key' => 'saas:'.$item['route'].':'.http_build_query($item['route_params'] ?? []),
                'label' => $item['label'],
                'route' => $item['route'],
                'url' => $this->saasNavigation->url($item),
                'icon' => $item['icon'],
                'breadcrumb' => $user->is_platform_admin ? 'Administration › SaaS Management' : 'Administration › Subscription',
                'group' => $user->is_platform_admin ? 'Administration' : 'Settings',
                'aliases' => [],
            ]);
        }

        return $entries
            ->filter(fn (array $entry): bool => filled($entry['url']))
            ->unique('navigation_key')
            ->values();
    }

    /** @return array<string, array<int, string>> */
    public function aliases(): array
    {
        return config('modules.navigation_search.aliases', []);
    }

    /** @return array{navigation_key:string,label:string,route:string,url:string,icon:string,breadcrumb:string,group:string,aliases:array<int,string>} */
    private function moduleEntry(Module $module, ?string $parentName = null): array
    {
        return [
            'navigation_key' => $module->navigationIdentity(),
            'label' => $module->name,
            'route' => $module->route,
            'url' => $module->url(),
            'icon' => $module->icon,
            'breadcrumb' => $module->category.($parentName ? ' › '.$parentName : ''),
            'group' => $this->groupFor($module->category),
            'aliases' => $module->searchAliases,
        ];
    }

    /** @return array<int, array{0:Module,1:?string}> */
    private function searchableModules(Module $module, ?string $parentName = null): array
    {
        $items = [];
        if ($module->navigable && $module->searchable) {
            $items[] = [$module, $parentName];
        }

        foreach ($module->children as $child) {
            $items = [...$items, ...$this->searchableModules($child, $module->name)];
        }

        return $items;
    }

    private function groupFor(string $category): string
    {
        return match ($category) {
            'Sales & CRM' => 'Sales',
            'Inventory & Supply Chain' => 'Inventory',
            'Purchases' => 'Purchases',
            'Customers' => 'Customers',
            'Administration' => 'Settings',
            default => $category,
        };
    }
}

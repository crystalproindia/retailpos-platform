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

    /** @return Collection<int, array{label:string,route:string,url:string,icon:string,breadcrumb:string,group:string,aliases:array<int,string>}> */
    public function entriesFor(User $user): Collection
    {
        $entries = collect();

        foreach ($this->modules->sidebarForUser($user) as $module) {
            $entries->push($this->moduleEntry($module));
            foreach ($module->children as $child) {
                $entries->push($this->moduleEntry($child, $module->name));
            }
        }

        foreach ([...$this->saasNavigation->platformItems($user), ...$this->saasNavigation->tenantSubscriptionItems($user)] as $item) {
            if (! Route::has($item['route'])) continue;
            $entries->push([
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
            ->unique('route')
            ->values();
    }

    /** @return array<string, array<int, string>> */
    public function aliases(): array
    {
        return config('modules.navigation_search.aliases', []);
    }

    /** @return array{label:string,route:string,url:string,icon:string,breadcrumb:string,group:string,aliases:array<int,string>} */
    private function moduleEntry(Module $module, ?string $parentName = null): array
    {
        return [
            'label' => $module->name,
            'route' => $module->route,
            'url' => $module->url(),
            'icon' => $module->icon,
            'breadcrumb' => $module->category.($parentName ? ' › '.$parentName : ''),
            'group' => $this->groupFor($module->category),
            'aliases' => $module->searchAliases,
        ];
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

<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsMenu;
use App\Models\Cms\CmsMenuItem;
use App\Models\User;
use App\Services\AuditLogger;

class CmsMenuService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createMenu(User $user, array $data): CmsMenu
    {
        $menu = CmsMenu::create($data + ['company_id' => $user->company_id]);
        $this->auditLogger->record('cms.menu.created', $menu, 'CMS menu created');

        return $menu;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMenu(CmsMenu $menu, array $data): CmsMenu
    {
        $menu->update($data);
        $this->auditLogger->record('cms.menu.updated', $menu, 'CMS menu updated');

        return $menu;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addItem(CmsMenu $menu, array $data): CmsMenuItem
    {
        $this->ensureParentBelongsToMenu($menu, $data['parent_id'] ?? null);
        $item = $menu->items()->create($data);
        $this->auditLogger->record('cms.menu_item.created', $item, 'CMS menu item created');

        return $item;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateItem(CmsMenu $menu, CmsMenuItem $item, array $data): CmsMenuItem
    {
        $this->ensureParentBelongsToMenu($menu, $data['parent_id'] ?? null, $item->id);
        $item->update($data);
        $this->auditLogger->record('cms.menu_item.updated', $item, 'CMS menu item updated');

        return $item;
    }

    public function restoreMenu(CmsMenu $menu): CmsMenu
    {
        $menu->restore();
        $this->auditLogger->record('cms.menu.restored', $menu, 'CMS menu restored');

        return $menu;
    }

    private function ensureParentBelongsToMenu(CmsMenu $menu, mixed $parentId, ?int $itemId = null): void
    {
        if (! $parentId) {
            return;
        }

        abort_unless((int) $parentId !== $itemId && $menu->items()->whereKey($parentId)->exists(), 422, 'The selected parent must belong to this menu.');
    }
}

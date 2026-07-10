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
        return CmsMenu::create($data + ['company_id' => $user->company_id]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateMenu(CmsMenu $menu, array $data): CmsMenu
    {
        $menu->update($data);

        return $menu;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addItem(CmsMenu $menu, array $data): CmsMenuItem
    {
        return $menu->items()->create($data);
    }

    public function restoreMenu(CmsMenu $menu): CmsMenu
    {
        $menu->restore();
        $this->auditLogger->record('cms.menu.restored', $menu, 'CMS menu restored');

        return $menu;
    }
}

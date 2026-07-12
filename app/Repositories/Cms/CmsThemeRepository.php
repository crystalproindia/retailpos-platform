<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsThemeSetting;

class CmsThemeRepository
{
    public function forCompany(int $companyId): CmsThemeSetting { return CmsThemeSetting::firstOrCreate(['company_id' => $companyId], ['website_theme_mode' => 'clean_light', 'primary_color' => '#0f766e', 'secondary_color' => '#0f172a', 'accent_color' => '#0284c7', 'background_color' => '#f8fafc', 'text_color' => '#0f172a', 'button_color' => '#0f766e', 'button_radius_style' => 'rounded', 'card_radius_style' => 'rounded']); }
}

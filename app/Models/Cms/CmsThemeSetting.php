<?php

namespace App\Models\Cms;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'primary_color', 'secondary_color', 'accent_color', 'background_color', 'text_color', 'button_color', 'button_radius_style', 'card_radius_style', 'website_theme_mode', 'header_style', 'footer_style', 'cta_button_style', 'settings'])]
class CmsThemeSetting extends Model
{
    protected function casts(): array { return ['settings' => 'array']; }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}

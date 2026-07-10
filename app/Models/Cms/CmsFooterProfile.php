<?php

namespace App\Models\Cms;

use App\Models\Company;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'company_name', 'address', 'phone', 'email', 'whatsapp', 'business_hours', 'google_map_url', 'copyright_text'])]
class CmsFooterProfile extends Model
{
    use Auditable;

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

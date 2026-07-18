<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsCaseStudy;
use App\Models\Cms\CmsPage;
use App\Models\Cms\CmsPreviewToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CmsPreviewService
{
    public function create(Model $model, User $user): array
    {
        $raw = Str::random(64);
        CmsPreviewToken::query()->where('company_id', $user->company_id)->where('previewable_type', $model::class)->where('previewable_id', $model->getKey())->whereNull('revoked_at')->update(['revoked_at' => now()]);
        CmsPreviewToken::create(['company_id' => $user->company_id, 'previewable_type' => $model::class, 'previewable_id' => $model->getKey(), 'token_hash' => hash('sha256', $raw), 'expires_at' => now()->addMinutes(30), 'created_by' => $user->id]);
        $kind = $model instanceof CmsPage ? 'page' : 'case-study';
        return ['token' => $raw, 'url' => url("/api/public/cms/preview/{$kind}/{$model->slug}?token={$raw}"), 'expires_at' => now()->addMinutes(30)];
    }

    public function revoke(Model $model, User $user): void { CmsPreviewToken::query()->where('company_id', $user->company_id)->where('previewable_type', $model::class)->where('previewable_id', $model->getKey())->whereNull('revoked_at')->update(['revoked_at' => now()]); }

    public function resolve(string $type, string $slug, string $raw): Model|null
    {
        $class = $type === 'page' ? CmsPage::class : CmsCaseStudy::class;
        $token = CmsPreviewToken::query()->where('previewable_type', $class)->where('token_hash', hash('sha256', $raw))->whereNull('revoked_at')->where('expires_at', '>', now())->first();
        if (! $token) return null;
        $model = $class::query()->whereKey($token->previewable_id)->where('slug', $slug)->where('company_id', $token->company_id)->first();
        if (! $model) return null;
        $token->update(['last_used_at' => now()]);
        return $model;
    }
}

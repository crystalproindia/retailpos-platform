<?php

namespace App\Repositories\Cms;

use App\Models\Cms\CmsRevalidationLog;

class CmsRevalidationLogRepository
{
    public function latestForCompany(int $companyId): ?CmsRevalidationLog
    {
        return CmsRevalidationLog::query()
            ->where('company_id', $companyId)
            ->latest('id')
            ->first();
    }

    public function latestByStatus(int $companyId, string $status): ?CmsRevalidationLog
    {
        return CmsRevalidationLog::query()
            ->where('company_id', $companyId)
            ->where('status', $status)
            ->latest('id')
            ->first();
    }
}

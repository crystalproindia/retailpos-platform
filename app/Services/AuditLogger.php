<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(string $event, ?Model $auditable = null, ?string $description = null, array $properties = []): ?AuditLog
    {
        if (! Schema::hasTable('audit_logs')) {
            return null;
        }

        $user = auth()->user();

        return AuditLog::create([
            'company_id' => $this->companyId($auditable, $user, $properties),
            'user_id' => $user?->id,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'description' => $description ?? str($event)->replace('.', ' ')->headline()->toString(),
            'properties' => $properties ?: null,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function companyId(?Model $auditable, ?User $user, array $properties): ?int
    {
        if (isset($properties['company_id'])) {
            return (int) $properties['company_id'];
        }

        if ($auditable instanceof Company) {
            return $auditable->id;
        }

        if ($auditable && isset($auditable->company_id)) {
            return $auditable->company_id;
        }

        return $user?->company_id;
    }
}

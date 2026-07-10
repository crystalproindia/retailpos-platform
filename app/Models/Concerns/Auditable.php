<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(fn (Model $model) => app(AuditLogger::class)->record(
            'created',
            $model,
            class_basename($model).' created',
        ));

        static::updated(fn (Model $model) => app(AuditLogger::class)->record(
            'updated',
            $model,
            class_basename($model).' updated',
            ['changes' => $model->getChanges()],
        ));

        static::deleted(fn (Model $model) => app(AuditLogger::class)->record(
            'deleted',
            $model,
            class_basename($model).' deleted',
        ));
    }
}

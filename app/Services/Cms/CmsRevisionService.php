<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CmsRevisionService
{
    /** @param array<string, mixed> $snapshot */
    public function record(Model $model, ?User $user, string $action, array $snapshot, ?array $before = null, ?string $summary = null): ?CmsRevision
    {
        $snapshot = $this->redact($snapshot);
        $changed = $before === null ? array_keys($snapshot) : $this->changedFields($snapshot, $this->redact($before));

        if ($before !== null && $changed === []) return null;

        return CmsRevision::create([
            'company_id' => $model->getAttribute('company_id'),
            'revisionable_type' => $model::class,
            'revisionable_id' => $model->getKey(),
            'revision_number' => (int) CmsRevision::query()->where('revisionable_type', $model::class)->where('revisionable_id', $model->getKey())->max('revision_number') + 1,
            'action' => $action,
            'snapshot' => $snapshot,
            'changed_fields' => $changed,
            'change_summary' => $summary,
            'created_by' => $user?->id,
        ]);
    }

    /** @param array<string, mixed> $snapshot @return array<string, mixed> */
    private function redact(array $snapshot): array
    {
        foreach ($snapshot as $key => $value) {
            if (preg_match('/(token|secret|password|api[_-]?key)/i', (string) $key)) { unset($snapshot[$key]); continue; }
            if (is_array($value)) $snapshot[$key] = $this->redact($value);
        }
        return $snapshot;
    }

    /** @param array<string, mixed> $after @param array<string, mixed> $before @return array<int, string> */
    private function changedFields(array $after, array $before): array
    {
        $keys = array_unique(array_merge(array_keys($after), array_keys($before)));
        return array_values(array_filter($keys, fn (string $key): bool => json_encode($after[$key] ?? null) !== json_encode($before[$key] ?? null)));
    }
}

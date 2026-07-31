<?php

namespace App\Services\Crm;

use App\Enums\Crm\LeadStageType;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\User;
use App\Repositories\Crm\LeadMasterDataRepository;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadMasterDataService
{
    public function __construct(
        private readonly LeadMasterDataRepository $repository,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $data */
    public function createStatus(User $user, array $data): CrmLeadStatus
    {
        return DB::transaction(function () use ($user, $data): CrmLeadStatus {
            $this->ensureUniqueName(CrmLeadStatus::class, $user->company_id, $data['name']);
            $stage = LeadStageType::from($data['stage_type']);
            $status = CrmLeadStatus::create($this->statusPayload($data, $stage) + [
                'company_id' => $user->company_id,
                'slug' => $this->availableSlug(CrmLeadStatus::class, $user->company_id, $data['name']),
                'sort_order' => ((int) CrmLeadStatus::query()->where('company_id', $user->company_id)->max('sort_order')) + 1,
                'is_active' => true,
                'is_default' => false,
            ]);

            if (($data['is_default'] ?? false) || $this->repository->statusesFor($user)->count() === 1) {
                $this->setDefaultStatus($user, $status);
            }

            $this->auditLogger->record('crm.lead_status.created', $status, 'CRM lead status created');

            return $status->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function updateStatus(User $user, CrmLeadStatus $status, array $data): CrmLeadStatus
    {
        return DB::transaction(function () use ($user, $status, $data): CrmLeadStatus {
            $this->ensureUniqueName(CrmLeadStatus::class, $user->company_id, $data['name'], $status->id);
            $status->update($this->statusPayload($data, LeadStageType::from($data['stage_type'])));

            if ($data['is_default'] ?? false) {
                $this->setDefaultStatus($user, $status);
            }

            $this->auditLogger->record('crm.lead_status.updated', $status, 'CRM lead status updated');

            return $status->refresh();
        });
    }

    public function toggleStatus(User $user, CrmLeadStatus $status): CrmLeadStatus
    {
        return DB::transaction(function () use ($user, $status): CrmLeadStatus {
            if ($status->is_active && $status->is_default) {
                $replacement = CrmLeadStatus::query()
                    ->where('company_id', $user->company_id)
                    ->where('is_active', true)
                    ->whereKeyNot($status->id)
                    ->orderBy('sort_order')
                    ->first();

                if (! $replacement) {
                    throw ValidationException::withMessages(['status' => 'Keep at least one active lead status available for new leads.']);
                }

                $this->setDefaultStatus($user, $replacement);
            }

            $status->update(['is_active' => ! $status->is_active]);
            $this->auditLogger->record('crm.lead_status.activation_changed', $status, 'CRM lead status activation changed');

            return $status->refresh();
        });
    }

    public function makeStatusDefault(User $user, CrmLeadStatus $status): CrmLeadStatus
    {
        return DB::transaction(function () use ($user, $status): CrmLeadStatus {
            $this->setDefaultStatus($user, $status);
            $this->auditLogger->record('crm.lead_status.default_changed', $status, 'CRM lead default status changed');

            return $status->refresh();
        });
    }

    /** @param array<int, int|string> $ids */
    public function reorderStatuses(User $user, array $ids): void
    {
        $this->reorder($user, CrmLeadStatus::class, $ids, 'crm.lead_status.reordered', 'CRM lead statuses reordered');
    }

    public function moveStatus(User $user, CrmLeadStatus $status, string $direction): void
    {
        $this->move($user, CrmLeadStatus::class, $status, $direction, 'crm.lead_status.reordered', 'CRM lead statuses reordered');
    }

    public function deleteStatus(User $user, CrmLeadStatus $status): void
    {
        DB::transaction(function () use ($user, $status): void {
            if ($status->leads()->exists()) {
                throw ValidationException::withMessages(['status' => 'This status is used by existing leads and can only be deactivated.']);
            }

            if ($status->is_default) {
                $replacement = CrmLeadStatus::query()
                    ->where('company_id', $user->company_id)
                    ->where('is_active', true)
                    ->whereKeyNot($status->id)
                    ->orderBy('sort_order')
                    ->first();

                if (! $replacement) {
                    throw ValidationException::withMessages(['status' => 'Create another active status before deleting the default status.']);
                }

                $this->setDefaultStatus($user, $replacement);
            }

            $status->delete();
            $this->auditLogger->record('crm.lead_status.deleted', $status, 'CRM lead status deleted');
        });
    }

    /** @param array<string, mixed> $data */
    public function createSource(User $user, array $data): CrmLeadSource
    {
        return DB::transaction(function () use ($user, $data): CrmLeadSource {
            $this->ensureUniqueName(CrmLeadSource::class, $user->company_id, $data['name']);
            $source = CrmLeadSource::create($this->sourcePayload($data) + [
                'company_id' => $user->company_id,
                'slug' => $this->availableSlug(CrmLeadSource::class, $user->company_id, $data['name']),
                'sort_order' => ((int) CrmLeadSource::query()->where('company_id', $user->company_id)->max('sort_order')) + 1,
                'is_active' => true,
                'is_default' => false,
            ]);

            if ($data['is_default'] ?? false) {
                $this->setDefaultSource($user, $source);
            }

            $this->auditLogger->record('crm.lead_source.created', $source, 'CRM lead source created');

            return $source->refresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function updateSource(User $user, CrmLeadSource $source, array $data): CrmLeadSource
    {
        return DB::transaction(function () use ($user, $source, $data): CrmLeadSource {
            $this->ensureUniqueName(CrmLeadSource::class, $user->company_id, $data['name'], $source->id);
            $source->update($this->sourcePayload($data));

            if ($data['is_default'] ?? false) {
                $this->setDefaultSource($user, $source);
            }

            $this->auditLogger->record('crm.lead_source.updated', $source, 'CRM lead source updated');

            return $source->refresh();
        });
    }

    public function toggleSource(User $user, CrmLeadSource $source): CrmLeadSource
    {
        return DB::transaction(function () use ($user, $source): CrmLeadSource {
            $source->update([
                'is_active' => ! $source->is_active,
                'is_default' => $source->is_active ? false : $source->is_default,
            ]);
            $this->auditLogger->record('crm.lead_source.activation_changed', $source, 'CRM lead source activation changed');

            return $source->refresh();
        });
    }

    public function makeSourceDefault(User $user, CrmLeadSource $source): CrmLeadSource
    {
        return DB::transaction(function () use ($user, $source): CrmLeadSource {
            $this->setDefaultSource($user, $source);
            $this->auditLogger->record('crm.lead_source.default_changed', $source, 'CRM lead default source changed');

            return $source->refresh();
        });
    }

    /** @param array<int, int|string> $ids */
    public function reorderSources(User $user, array $ids): void
    {
        $this->reorder($user, CrmLeadSource::class, $ids, 'crm.lead_source.reordered', 'CRM lead sources reordered');
    }

    public function moveSource(User $user, CrmLeadSource $source, string $direction): void
    {
        $this->move($user, CrmLeadSource::class, $source, $direction, 'crm.lead_source.reordered', 'CRM lead sources reordered');
    }

    public function deleteSource(User $user, CrmLeadSource $source): void
    {
        DB::transaction(function () use ($source): void {
            if ($source->leads()->exists()) {
                throw ValidationException::withMessages(['source' => 'This source is used by existing leads and can only be deactivated.']);
            }

            $source->delete();
            $this->auditLogger->record('crm.lead_source.deleted', $source, 'CRM lead source deleted');
        });
    }

    private function setDefaultStatus(User $user, CrmLeadStatus $status): void
    {
        if (! $status->is_active) {
            throw ValidationException::withMessages(['status' => 'Only an active status can be the default.']);
        }

        CrmLeadStatus::query()->where('company_id', $user->company_id)->update(['is_default' => false]);
        $status->update(['is_default' => true]);
    }

    private function setDefaultSource(User $user, CrmLeadSource $source): void
    {
        if (! $source->is_active) {
            throw ValidationException::withMessages(['source' => 'Only an active source can be the default.']);
        }

        CrmLeadSource::query()->where('company_id', $user->company_id)->update(['is_default' => false]);
        $source->update(['is_default' => true]);
    }

    /** @param class-string<Model> $model @param array<int, int|string> $ids */
    private function reorder(User $user, string $model, array $ids, string $event, string $description): void
    {
        $normalised = array_values(array_unique(array_map('intval', $ids)));
        $records = $model::query()->where('company_id', $user->company_id)->whereIn('id', $normalised)->get()->keyBy('id');

        if (count($normalised) !== $records->count()) {
            throw ValidationException::withMessages(['ids' => 'The requested ordering contains an unavailable record.']);
        }

        DB::transaction(function () use ($normalised, $records, $event, $description): void {
            foreach ($normalised as $position => $id) {
                $records[$id]->update(['sort_order' => $position + 1]);
            }

            $this->auditLogger->record($event, null, $description, ['company_id' => $records->first()->company_id]);
        });
    }

    /** @param class-string<Model> $model */
    private function move(User $user, string $model, Model $record, string $direction, string $event, string $description): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['direction' => 'Choose a valid ordering direction.']);
        }

        $ids = $model::query()
            ->where('company_id', $user->company_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id')
            ->all();
        $position = array_search($record->id, $ids, true);
        $target = $position === false ? false : $position + ($direction === 'up' ? -1 : 1);

        if ($target === false || ! isset($ids[$target])) {
            return;
        }

        [$ids[$position], $ids[$target]] = [$ids[$target], $ids[$position]];
        $this->reorder($user, $model, $ids, $event, $description);
    }

    /** @param class-string<Model> $model */
    private function ensureUniqueName(string $model, int $companyId, string $name, ?int $ignoreId = null): void
    {
        $exists = $model::query()
            ->where('company_id', $companyId)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim($name))])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['name' => 'A lead master-data record with this name already exists.']);
        }
    }

    /** @param class-string<Model> $model */
    private function availableSlug(string $model, int $companyId, string $name): string
    {
        $base = Str::slug($name) ?: 'lead-master-data';
        $slug = $base;
        $suffix = 2;

        while ($model::query()->where('company_id', $companyId)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function statusPayload(array $data, LeadStageType $stage): array
    {
        return [
            'name' => trim($data['name']),
            'stage_type' => $stage,
            'color' => ($data['color'] ?? null) ?: null,
            'tone' => $data['tone'],
            'probability' => $data['probability'],
            'is_won' => $stage === LeadStageType::Won,
            'is_lost' => in_array($stage, [LeadStageType::Lost, LeadStageType::Spam], true),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function sourcePayload(array $data): array
    {
        return Arr::only([
            'name' => trim($data['name']),
            'description' => ($data['description'] ?? null) ?: null,
            'color' => ($data['color'] ?? null) ?: null,
            'tone' => $data['tone'],
        ], ['name', 'description', 'color', 'tone']);
    }
}

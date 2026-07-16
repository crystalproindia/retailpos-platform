<?php

namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\LeadStageType;
use App\Events\Domain\Crm\LeadCreated;
use App\Models\Company;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadSource;
use App\Models\Crm\CrmLeadStatus;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicLeadIntakeService
{
    /** @var array<string, array{name: string, slug: string}> */
    private const SOURCES = [
        'contact' => ['name' => 'Website Contact', 'slug' => 'website-contact'],
        'book_demo' => ['name' => 'Book Demo', 'slug' => 'book-demo'],
        'pricing_enquiry' => ['name' => 'Pricing Enquiry', 'slug' => 'pricing-enquiry'],
        'landing_page' => ['name' => 'Landing Page', 'slug' => 'landing-page'],
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function intake(array $data): CrmLead
    {
        $company = $this->company();
        $sourceDefinition = self::SOURCES[$data['source']];

        return DB::transaction(function () use ($company, $sourceDefinition, $data): CrmLead {
            $source = CrmLeadSource::firstOrCreate(
                ['company_id' => $company->id, 'slug' => $sourceDefinition['slug']],
                ['name' => $sourceDefinition['name'], 'is_active' => true, 'sort_order' => 100],
            );
            $status = CrmLeadStatus::firstOrCreate(
                ['company_id' => $company->id, 'slug' => 'new'],
                [
                    'name' => 'New',
                    'stage_type' => LeadStageType::New->value,
                    'probability' => 0,
                    'is_won' => false,
                    'is_lost' => false,
                    'is_active' => true,
                    'sort_order' => 1,
                ],
            );
            $assignee = $this->assignee($company);
            $metadata = $this->metadata($data);

            $lead = CrmLead::create([
                'company_id' => $company->id,
                'branch_id' => $assignee?->branch_id,
                'source_id' => $source->id,
                'status_id' => $status->id,
                'assigned_user_id' => $assignee?->id,
                'title' => $this->title($data, $sourceDefinition['name']),
                'business_name' => $this->clean($data['company_name'] ?? null),
                'contact_name' => $this->clean($data['name']),
                'email' => $this->clean($data['email'] ?? null),
                'phone' => $this->clean($data['phone'] ?? null),
                'city' => $this->clean($data['city'] ?? null),
                'country' => $this->clean($data['country'] ?? null),
                'business_type' => $this->clean($data['business_type'] ?? null),
                'description' => $this->clean($data['requirement'] ?? null),
                'priority' => LeadPriority::Medium->value,
                'metadata' => $metadata,
            ]);

            CrmActivity::create([
                'company_id' => $company->id,
                'crm_lead_id' => $lead->id,
                'assigned_user_id' => $assignee?->id,
                'type' => ActivityType::Note->value,
                'subject' => 'Public website lead received',
                'description' => $this->activityDescription($metadata),
                'completed_at' => now(),
                'priority' => LeadPriority::Medium->value,
            ]);

            $this->auditLogger->record('crm.lead.public_intake_received', $lead, 'Public website lead received', [
                'company_id' => $company->id,
                'source' => $sourceDefinition['slug'],
            ]);
            $this->domainEvents->dispatch(new LeadCreated(
                companyId: $lead->company_id,
                actorId: $assignee?->id,
                aggregateType: CrmLead::class,
                aggregateId: $lead->id,
                payload: [
                    'notification_type' => match ($data['source']) {
                        'book_demo' => 'demo_request_received',
                        'pricing_enquiry' => 'pricing_enquiry_received',
                        default => 'new_lead_received',
                    },
                    'lead_id' => $lead->id,
                    'lead_title' => $lead->title,
                    'business_name' => $lead->business_name,
                    'contact_name' => $lead->contact_name,
                    'email' => $lead->email,
                    'phone' => $lead->phone,
                    'business_type' => $lead->business_type,
                    'requirement' => $lead->description,
                    'source' => $sourceDefinition['slug'],
                    'source_name' => $sourceDefinition['name'],
                    'lead_type' => $data['source'],
                    'assigned_user_id' => $lead->assigned_user_id,
                    'channel' => 'public_website',
                ],
            ));

            return $lead;
        });
    }

    private function company(): Company
    {
        $configuredId = config('services.retailpos.public_lead_company_id');
        $company = Company::query()
            ->where('is_active', true)
            ->when($configuredId, fn ($query, $id) => $query->whereKey($id))
            ->orderBy('id')
            ->first();

        if (! $company) {
            throw ValidationException::withMessages(['source' => 'Public lead intake is not configured for an active company.']);
        }

        return $company;
    }

    private function assignee(Company $company): ?User
    {
        $configuredId = config('services.retailpos.public_lead_assignee_id');

        return User::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->when($configuredId, fn ($query, $id) => $query->whereKey($id))
            ->when(! $configuredId, fn ($query) => $query->whereIn('role', ['administrator', 'manager', 'sales']))
            ->orderByRaw("case role when 'administrator' then 1 when 'manager' then 2 else 3 end")
            ->orderBy('id')
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function metadata(array $data): array
    {
        return array_filter([
            'channel' => 'public_website',
            'lead_type' => $data['source'],
            'page_url' => $this->clean($data['page_url'] ?? null),
            'utm' => array_filter([
                'source' => $this->clean($data['utm_source'] ?? null),
                'medium' => $this->clean($data['utm_medium'] ?? null),
                'campaign' => $this->clean($data['utm_campaign'] ?? null),
            ]),
            'submitted_metadata' => $this->sanitizeMetadata(Arr::get($data, 'metadata', [])),
        ], fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function activityDescription(array $metadata): string
    {
        $parts = ['Public website form intake.'];

        if ($metadata['page_url'] ?? null) {
            $parts[] = 'Page: '.$metadata['page_url'];
        }

        if ($metadata['utm'] ?? []) {
            $parts[] = 'UTM: '.http_build_query($metadata['utm'], '', ', ');
        }

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function title(array $data, string $source): string
    {
        $company = $this->clean($data['company_name'] ?? null);

        return trim(($company ?: $this->clean($data['name'])).' - '.$source);
    }

    private function clean(mixed $value): ?string
    {
        if (! is_scalar($value) || $value === '') {
            return null;
        }

        return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value)));
    }

    private function sanitizeMetadata(mixed $value, int $depth = 0): mixed
    {
        if ($depth >= 4) {
            return null;
        }

        if (is_array($value)) {
            return collect(array_slice($value, 0, 20, true))
                ->mapWithKeys(fn (mixed $item, mixed $key): array => [(string) $this->clean($key) => $this->sanitizeMetadata($item, $depth + 1)])
                ->filter(fn (mixed $item, string $key): bool => $key !== '' && $item !== null)
                ->all();
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        return $this->clean($value);
    }
}

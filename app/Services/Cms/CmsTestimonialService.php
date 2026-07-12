<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsTestimonial;
use App\Models\User;
use App\Services\AuditLogger;

class CmsTestimonialService
{
    public function __construct(private readonly AuditLogger $auditLogger, private readonly CmsProEventService $events) {}
    /** @param array<string, mixed> $data */ public function create(User $user, array $data): CmsTestimonial { $item = CmsTestimonial::create($data + ['company_id' => $user->company_id]); $this->auditLogger->record('cms.testimonial.created', $item, 'Testimonial created'); $this->events->dispatch('cms.testimonial.created', $user, $item, ['client_name' => $item->client_name]); return $item; }
    /** @param array<string, mixed> $data */ public function update(CmsTestimonial $item, User $user, array $data): CmsTestimonial { $item->update($data); $this->auditLogger->record('cms.testimonial.updated', $item, 'Testimonial updated'); return $item->refresh(); }
    public function delete(CmsTestimonial $item): void { $item->delete(); $this->auditLogger->record('cms.testimonial.deleted', $item, 'Testimonial deleted'); }
    public function restore(CmsTestimonial $item): CmsTestimonial { $item->restore(); $this->auditLogger->record('cms.testimonial.restored', $item, 'Testimonial restored'); return $item->refresh(); }
}

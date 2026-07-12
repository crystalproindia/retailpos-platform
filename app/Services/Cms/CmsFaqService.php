<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsFaq;
use App\Models\User;
use App\Services\AuditLogger;

class CmsFaqService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}
    /** @param array<string, mixed> $data */ public function create(User $user, array $data): CmsFaq { $faq = CmsFaq::create($data + ['company_id' => $user->company_id]); $this->auditLogger->record('cms.faq.created', $faq, 'FAQ created'); return $faq; }
    /** @param array<string, mixed> $data */ public function update(CmsFaq $faq, array $data): CmsFaq { $faq->update($data); $this->auditLogger->record('cms.faq.updated', $faq, 'FAQ updated'); return $faq->refresh(); }
    public function delete(CmsFaq $faq): void { $faq->delete(); $this->auditLogger->record('cms.faq.deleted', $faq, 'FAQ deleted'); }
    public function restore(CmsFaq $faq): CmsFaq { $faq->restore(); $this->auditLogger->record('cms.faq.restored', $faq, 'FAQ restored'); return $faq->refresh(); }
}

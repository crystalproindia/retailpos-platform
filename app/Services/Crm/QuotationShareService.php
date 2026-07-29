<?php

namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\QuotationStatus;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmQuotation;
use App\Models\Crm\CrmQuotationShare;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\EmailDeliveryService;

class QuotationShareService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly QuotationService $quotations,
        private readonly EmailDeliveryService $emailDelivery,
    ) {}

    /** @return array{message: string, url: ?string, phone: ?string} */
    public function whatsappPayload(CrmQuotation $quotation, ?string $publicLink = null): array
    {
        $publicLink ??= 'A secure proposal link will be generated before sending.';
        $message = collect([
            'Hello '.($quotation->customer_name ?: $quotation->customer_company ?: 'there').', please find your RetailPOS proposal '.$quotation->quotation_number.'.',
            'You can view it here: '.$publicLink,
            'Valid until: '.($quotation->valid_until?->format('d M Y') ?: 'Not specified'),
            'Thank you,',
            'RetailPOS.biz',
        ])->implode("\n");
        $phone = $this->normalizePhone($quotation->customer_phone);

        return [
            'message' => $message,
            'phone' => $phone,
            'url' => $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode($message) : null,
        ];
    }

    /** @return array{message: string, url: ?string, phone: ?string} */
    public function prepareWhatsApp(CrmQuotation $quotation, User $user): array
    {
        $link = $this->quotations->issuePublicLink($quotation, $user);
        $quotation = $link->quotation;
        $payload = $this->whatsappPayload($quotation, $link->url);
        $recipient = $payload['phone'];

        $quotation->shares()->create([
            'channel' => 'whatsapp',
            'recipient' => $recipient,
            'status' => 'prepared',
            'metadata' => ['has_phone' => $recipient !== null],
            'created_by' => $user->id,
        ]);
        $this->recordActivity($quotation, $user, 'Quotation share message prepared for WhatsApp.');
        $this->auditLogger->record('crm.quotation.whatsapp_prepared', $quotation, 'Quotation WhatsApp share message prepared', [
            'company_id' => $quotation->company_id,
            'has_recipient' => $recipient !== null,
        ]);

        return $payload;
    }

    /** @param array{to_email: string, subject: string, message_body: string, attach_pdf?: bool} $data
     * @param array<int, string> $cc
     * @return array{queued: bool, configured: bool, attachment_unavailable: bool}
     */
    public function sendEmail(CrmQuotation $quotation, User $user, array $data, array $cc = []): array
    {
        $link = $this->quotations->issuePublicLink($quotation, $user);
        $quotation = $link->quotation;
        $share = $quotation->shares()->create([
            'channel' => 'email',
            'recipient' => $data['to_email'],
            'status' => 'prepared',
            'metadata' => ['cc' => $cc, 'attachment_requested' => (bool) ($data['attach_pdf'] ?? false)],
            'created_by' => $user->id,
        ]);
        $delivery = $this->emailDelivery->queue(
            companyId: $quotation->company_id,
            recipient: $data['to_email'],
            subject: $data['subject'],
            templateKey: 'quotation_sent',
            payload: [
                'heading' => 'Your RetailPOS quotation is ready',
                'greeting' => 'Hello '.($quotation->customer_name ?: $quotation->customer_company ?: 'there').',',
                'message' => $data['message_body'],
                'details' => [
                    'Quotation' => $quotation->quotation_number,
                    'Total' => $quotation->currency.' '.number_format((float) $quotation->grand_total, 2),
                    'Valid until' => $quotation->valid_until?->format('d M Y') ?: 'Not specified',
                ],
                'action_url' => $link->url,
                'action_label' => 'View quotation',
            ],
            related: $quotation,
            createdBy: $user,
            idempotencyKey: 'quotation-email:'.$quotation->id.':'.hash('sha256', strtolower($data['to_email']).'|'.$data['subject'].'|'.$data['message_body']),
            recipientName: $quotation->customer_name,
        );

        if ($quotation->status === QuotationStatus::Draft && $delivery->status !== 'skipped_not_configured') {
            $quotation = $this->quotations->markSent($quotation, $user);
        }
        $share->update([
            'status' => $delivery->status,
            'sent_at' => $delivery->status === 'sent' ? now() : null,
            'metadata' => array_merge($share->metadata ?? [], [
                'delivery_id' => $delivery->id,
                'attachment_included' => false,
                'attachment_unavailable' => (bool) ($data['attach_pdf'] ?? false),
            ]),
        ]);
        $this->recordActivity($quotation, $user, "Quotation {$quotation->quotation_number} email queued.");
        $this->auditLogger->record('crm.quotation.email_queued', $quotation, 'Quotation email queued', [
            'company_id' => $quotation->company_id,
            'delivery_id' => $delivery->id,
        ]);

        return [
            'queued' => $delivery->status === 'queued',
            'configured' => $delivery->status !== 'skipped_not_configured',
            'attachment_unavailable' => (bool) ($data['attach_pdf'] ?? false),
        ];
    }

    public function recordPdfDownload(CrmQuotation $quotation, User $user): void
    {
        $quotation->shares()->create([
            'channel' => 'pdf_download',
            'status' => 'downloaded',
            'created_by' => $user->id,
        ]);
        $this->auditLogger->record('crm.quotation.pdf_downloaded', $quotation, 'Quotation PDF downloaded', ['company_id' => $quotation->company_id]);
    }

    /** @return array{to_email: string, subject: string, message_body: string} */
    public function emailDefaults(CrmQuotation $quotation, ?string $publicLink = null): array
    {
        $publicLink ??= 'A secure proposal link will be included when this email is sent.';

        return [
            'to_email' => $quotation->customer_email ?: '',
            'subject' => 'RetailPOS Proposal - '.$quotation->quotation_number,
            'message_body' => implode("\n", [
                'Hello '.($quotation->customer_name ?: $quotation->customer_company ?: 'there').',',
                '',
                'Thank you for your interest in RetailPOS.biz.',
                '',
                'Please find your proposal here:',
                $publicLink,
                '',
                'Quotation Number: '.$quotation->quotation_number,
                'Valid Until: '.($quotation->valid_until?->format('d M Y') ?: 'Not specified'),
                'Total: '.$quotation->currency.' '.number_format((float) $quotation->grand_total, 2),
                '',
                'Regards,',
                'RetailPOS.biz',
                'Powered by CrystalPro',
            ]),
        ];
    }

    private function recordActivity(CrmQuotation $quotation, User $user, string $subject): void
    {
        CrmActivity::create([
            'company_id' => $quotation->company_id,
            'crm_lead_id' => $quotation->lead_id,
            'assigned_user_id' => $quotation->lead?->assigned_user_id,
            'created_by' => $user->id,
            'type' => ActivityType::Note,
            'subject' => $subject,
            'description' => $subject,
            'scheduled_at' => now(),
            'completed_at' => now(),
            'priority' => $quotation->lead?->priority ?? LeadPriority::Medium,
        ]);
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (! $digits) {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '91'.substr($digits, 1);
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? $digits : null;
    }
}

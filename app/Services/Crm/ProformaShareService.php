<?php

namespace App\Services\Crm;

use App\Enums\Crm\ActivityType;
use App\Enums\Crm\LeadPriority;
use App\Enums\Crm\ProformaStatus;
use App\Events\Domain\Crm\ProformaEvent;
use App\Mail\CrmProformaShareMail;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmProformaInvoice;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Events\DomainEventDispatcher;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProformaShareService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DomainEventDispatcher $domainEvents,
        private readonly ProformaPdfService $pdf,
        private readonly ProformaService $proformas,
    ) {}

    /** @return array{message: string, url: ?string, phone: ?string} */
    public function whatsappPayload(CrmProformaInvoice $proforma): array
    {
        $publicLink = $proforma->public_url ?: 'A secure proforma link will be generated before sending.';
        $message = implode("\n", [
            'Hello '.($proforma->customer_name ?: $proforma->customer_company ?: 'there').', please find your RetailPOS proforma invoice '.$proforma->proforma_number.'.',
            '',
            'View here: '.$publicLink,
            'Total: '.$proforma->currency.' '.number_format((float) $proforma->grand_total, 2),
            'Paid: '.$proforma->currency.' '.number_format((float) $proforma->paid_amount, 2),
            'Balance: '.$proforma->currency.' '.number_format((float) $proforma->balance_amount, 2),
            'Due Date: '.($proforma->due_date?->format('d M Y') ?: 'Not specified'),
            '',
            'Thank you,',
            'RetailPOS.biz',
            'Powered by CrystalPro',
        ]);
        $phone = $this->normalizePhone($proforma->customer_phone);

        return [
            'message' => $message,
            'phone' => $phone,
            'url' => $phone ? 'https://wa.me/'.$phone.'?text='.rawurlencode($message) : null,
        ];
    }

    /** @return array{message: string, url: ?string, phone: ?string} */
    public function prepareWhatsApp(CrmProformaInvoice $proforma, User $user): array
    {
        $proforma = $this->proformas->link($proforma, $user);
        $payload = $this->whatsappPayload($proforma);
        $proforma->shares()->create([
            'channel' => 'whatsapp',
            'recipient' => $payload['phone'],
            'status' => 'prepared',
            'metadata' => [
                'has_phone' => $payload['phone'] !== null,
                'wa_me_url' => $payload['url'],
                'message_preview' => $payload['message'],
            ],
            'created_by' => $user->id,
        ]);
        $this->recordActivity($proforma, $user, 'Proforma WhatsApp share prepared.');
        $this->auditLogger->record('crm.proforma.whatsapp_prepared', $proforma, 'Proforma WhatsApp share prepared', [
            'company_id' => $proforma->company_id,
            'recipient' => $payload['phone'],
        ]);

        return $payload;
    }

    /** @param array{to_email: string, subject: string, message_body: string, attach_pdf?: bool} $data
     * @param array<int, string> $cc
     * @return array{sent: bool, attachment_unavailable: bool}
     */
    public function sendEmail(CrmProformaInvoice $proforma, User $user, array $data, array $cc = []): array
    {
        $proforma = $this->proformas->link($proforma, $user);
        $share = $proforma->shares()->create([
            'channel' => 'email',
            'recipient' => $data['to_email'],
            'status' => 'prepared',
            'metadata' => ['cc' => $cc, 'subject' => $data['subject'], 'attachment_requested' => (bool) ($data['attach_pdf'] ?? false)],
            'created_by' => $user->id,
        ]);
        $attachmentUnavailable = false;
        $pdfBinary = null;

        if ($data['attach_pdf'] ?? false) {
            try {
                $pdfBinary = $this->pdf->binary($proforma);
            } catch (Throwable $exception) {
                report($exception);
                $attachmentUnavailable = true;
            }
        }

        try {
            $mail = Mail::to($data['to_email']);
            if ($cc !== []) {
                $mail->cc($cc);
            }
            $mail->send(new CrmProformaShareMail(
                proforma: $proforma,
                emailSubject: $data['subject'],
                messageBody: $data['message_body'],
                pdfBinary: $pdfBinary,
                pdfFilename: $pdfBinary ? $this->pdf->filename($proforma) : null,
            ));

            if ($proforma->status === ProformaStatus::Draft) {
                $proforma = $this->proformas->markSent($proforma, $user);
            }

            $share->update([
                'status' => 'sent',
                'sent_at' => now(),
                'metadata' => array_merge($share->metadata ?? [], ['attachment_included' => $pdfBinary !== null]),
            ]);
            $this->recordActivity($proforma, $user, "Proforma invoice {$proforma->proforma_number} sent by email to {$data['to_email']}.");
            $this->auditLogger->record('crm.proforma.email_sent', $proforma, 'Proforma invoice sent by email', [
                'company_id' => $proforma->company_id,
                'recipient' => $data['to_email'],
            ]);

            return ['sent' => true, 'attachment_unavailable' => $attachmentUnavailable];
        } catch (Throwable $exception) {
            report($exception);
            $share->update([
                'status' => 'failed',
                'failed_at' => now(),
                'metadata' => array_merge($share->metadata ?? [], ['failure' => 'mail_delivery_failed']),
            ]);
            $this->domainEvents->dispatch($this->event('crm.proforma.share_failed', $proforma, $user, [
                'channel' => 'email',
                'recipient' => $data['to_email'],
            ]));

            return ['sent' => false, 'attachment_unavailable' => $attachmentUnavailable];
        }
    }

    /** @return array{to_email: string, subject: string, message_body: string} */
    public function emailDefaults(CrmProformaInvoice $proforma): array
    {
        $publicLink = $proforma->public_url ?: 'A secure proforma link will be included when this email is sent.';

        return [
            'to_email' => $proforma->customer_email ?: '',
            'subject' => 'RetailPOS Proforma Invoice - '.$proforma->proforma_number,
            'message_body' => implode("\n", [
                'Hello '.($proforma->customer_name ?: $proforma->customer_company ?: 'there').',',
                '',
                'Please find your RetailPOS proforma invoice here:',
                $publicLink,
                '',
                'Proforma Number: '.$proforma->proforma_number,
                'Total: '.$proforma->currency.' '.number_format((float) $proforma->grand_total, 2),
                'Paid: '.$proforma->currency.' '.number_format((float) $proforma->paid_amount, 2),
                'Balance: '.$proforma->currency.' '.number_format((float) $proforma->balance_amount, 2),
                'Due Date: '.($proforma->due_date?->format('d M Y') ?: 'Not specified'),
                '',
                'Regards,',
                'RetailPOS.biz',
                'Powered by CrystalPro',
            ]),
        ];
    }

    private function recordActivity(CrmProformaInvoice $proforma, User $user, string $subject): void
    {
        CrmActivity::create([
            'company_id' => $proforma->company_id,
            'crm_lead_id' => $proforma->lead_id,
            'assigned_user_id' => $proforma->lead?->assigned_user_id,
            'created_by' => $user->id,
            'type' => ActivityType::Note,
            'subject' => $subject,
            'description' => $subject,
            'scheduled_at' => now(),
            'completed_at' => now(),
            'priority' => $proforma->lead?->priority ?? LeadPriority::Medium,
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function event(string $key, CrmProformaInvoice $proforma, User $user, array $extra = []): ProformaEvent
    {
        return new ProformaEvent($key, $proforma->company_id, $user->id, CrmProformaInvoice::class, $proforma->id, array_merge([
            'proforma_id' => $proforma->id,
            'proforma_number' => $proforma->proforma_number,
            'lead_id' => $proforma->lead_id,
            'assigned_user_id' => $proforma->lead?->assigned_user_id,
        ], $extra));
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

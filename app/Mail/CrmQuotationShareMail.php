<?php

namespace App\Mail;

use App\Models\Crm\CrmQuotation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CrmQuotationShareMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CrmQuotation $quotation,
        public readonly string $emailSubject,
        public readonly string $messageBody,
        public readonly ?string $pdfBinary = null,
        public readonly ?string $pdfFilename = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->emailSubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.crm.quotation-share');
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if (! $this->pdfBinary || ! $this->pdfFilename) {
            return [];
        }

        return [Attachment::fromData(fn (): string => $this->pdfBinary, $this->pdfFilename)->withMime('application/pdf')];
    }
}

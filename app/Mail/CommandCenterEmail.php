<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class CommandCenterEmail extends Mailable
{
    use Queueable;

    /** @param array<string, string> $details */
    public function __construct(
        public readonly string $emailSubject,
        public readonly string $heading,
        public readonly string $greeting,
        public readonly string $messageText,
        public readonly array $details = [],
        public readonly ?string $actionUrl = null,
        public readonly ?string $actionLabel = null,
        public readonly ?string $fromAddress = null,
        public readonly ?string $fromName = null,
        public readonly ?string $replyToAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: str($this->emailSubject)->replace(["\r", "\n"], ' ')->limit(180)->toString(),
            from: $this->fromAddress ? new Address($this->fromAddress, $this->fromName ?: config('app.name')) : null,
            replyTo: $this->replyToAddress ? [new Address($this->replyToAddress)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.command-center');
    }
}

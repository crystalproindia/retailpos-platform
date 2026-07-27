<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PublicSignupVerificationCode extends Mailable
{
    public function __construct(public readonly string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your RetailPOS verification code');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.public-signup-verification-code');
    }
}

<?php

namespace App\Mail;

use App\Models\Applicant\Applicant;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationResetToPendingMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Applicant $applicant
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: 'Update on Your CNHS Application Status',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-reset-to-pending',
        );
    }
}

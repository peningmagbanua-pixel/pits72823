<?php

namespace App\Mail;

use App\Models\Admin\Program;
use App\Models\Applicant\Applicant;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationSubmittedMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Applicant $applicant,
        public Program $program
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: 'We Received Your CNHS Application',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-submitted',
        );
    }
}

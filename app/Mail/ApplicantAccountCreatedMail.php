<?php

namespace App\Mail;

use App\Models\Applicant\Applicant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicantAccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $plainPassword;
    public string $loginUrl;

    public function __construct(
        public Applicant $applicant,
        string $plainPassword
    ) {
        $this->plainPassword = $plainPassword;
        $this->loginUrl = config('app.url') . '/applicant/login';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your CNHS Application Account Has Been Created',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.applicant-account-created',
        );
    }
}

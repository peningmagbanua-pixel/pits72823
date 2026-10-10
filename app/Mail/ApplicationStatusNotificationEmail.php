<?php

namespace App\Mail;

use App\Models\Applicant\Applicant;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusNotificationEmail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Applicant $applicant,
        public string $status,
        public ?string $adminMessage = null
    ) {}

    public function envelope(): Envelope
    {
        $statusLabel = match($this->status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'under_review' => 'Under Review',
            'exam_scheduled' => 'Exam Scheduled',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };

        return new Envelope(
            from: config('mail.from.address'),
            subject: 'Application Status Update - ' . $statusLabel,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-status-notification',
        );
    }
}

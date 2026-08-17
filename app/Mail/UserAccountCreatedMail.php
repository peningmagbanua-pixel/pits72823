<?php

namespace App\Mail;

use App\Models\Admin\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserAccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $plainPassword;
    public string $loginUrl;

    public function __construct(
        public User $user,
        string $plainPassword
    ) {
        $this->plainPassword = $plainPassword;
        $this->loginUrl = config('app.url') . '/admin/login';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your CNHS System Account Has Been Created',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-account-created',
        );
    }
}

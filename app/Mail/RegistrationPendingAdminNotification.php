<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RegistrationPendingAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $registrant,
        public string $registrationType,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New '.$this->registrationType.' registration pending approval',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.registration-pending-admin',
            with: [
                'registrant' => $this->registrant,
                'registrationType' => $this->registrationType,
                'approvalsUrl' => url('/admin/sellers?approval_status=pending'),
            ],
        );
    }
}

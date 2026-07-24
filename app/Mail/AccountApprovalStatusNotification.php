<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountApprovalStatusNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $status,
        public ?string $reason = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->status) {
            'approved' => 'Your RML account has been approved',
            'rejected' => 'Your RML account registration was not approved',
            'suspended' => 'Your RML account has been suspended',
            default => 'RML account status update',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.account-approval-status',
            with: [
                'user' => $this->user,
                'status' => $this->status,
                'reason' => $this->reason,
                'loginUrl' => url('/login'),
            ],
        );
    }
}

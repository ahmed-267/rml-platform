<?php

namespace App\Mail;

use App\Models\SellerStaffInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SellerStaffInvitation $invitation,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been invited to join RML as seller staff',
        );
    }

    public function content(): Content
    {
        $this->invitation->loadMissing('company');

        return new Content(
            markdown: 'mail.staff-invitation',
            with: [
                'invitation' => $this->invitation,
                'companyName' => $this->invitation->company?->name ?? 'your company',
                'acceptUrl' => url('/seller/staff/invitations/'.$this->invitation->token.'/accept'),
                'expiresAt' => $this->invitation->expires_at?->toFormattedDateString(),
            ],
        );
    }
}

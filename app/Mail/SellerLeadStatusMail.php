<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerLeadStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  'accepted'|'rejected'|'info_requested'  $event
     */
    public function __construct(
        public Lead $lead,
        public string $event,
        public ?string $details = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->event) {
            'accepted' => __('rml.mail.lead_accepted_subject', [
                'reference' => $this->lead->lead_reference,
            ]),
            'rejected' => __('rml.mail.lead_rejected_subject', [
                'reference' => $this->lead->lead_reference,
            ]),
            default => __('rml.mail.lead_info_requested_subject', [
                'reference' => $this->lead->lead_reference,
            ]),
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $statusLabel = match ($this->event) {
            'accepted' => __('rml.lead_statuses.listed'),
            'rejected' => __('rml.lead_statuses.rejected'),
            default => __('rml.lead_statuses.needs_more_information'),
        };

        return new Content(
            markdown: 'mail.seller-lead-status',
            with: [
                'lead' => $this->lead,
                'reference' => $this->lead->lead_reference,
                'event' => $this->event,
                'statusLabel' => $statusLabel,
                'details' => $this->details,
                'leadUrl' => url('/seller/leads/'.$this->lead->id),
                'brand' => __('rml.brand.footer_brand'),
            ],
        );
    }
}

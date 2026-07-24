<?php

namespace App\Mail;

use App\Models\Commission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerCommissionDueMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Commission $commission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'RML commission due — '.$this->commission->commission_reference);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.seller-commission-due',
            with: [
                'commission' => $this->commission,
                'reference' => $this->commission->commission_reference,
                'amount' => $this->commission->commission_amount,
            ],
        );
    }
}

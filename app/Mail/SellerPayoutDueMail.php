<?php

namespace App\Mail;

use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerPayoutDueMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Payout $payout) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'RML payout due — '.$this->payout->payout_reference);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.seller-payout-due',
            with: [
                'payout' => $this->payout,
                'reference' => $this->payout->payout_reference,
                'amount' => $this->payout->amount,
            ],
        );
    }
}

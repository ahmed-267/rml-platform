<?php

namespace App\Mail;

use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SellerPayoutPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Payout $payout) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'RML payout paid — '.$this->payout->payout_reference);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.seller-payout-paid',
            with: [
                'payout' => $this->payout,
                'reference' => $this->payout->payout_reference,
                'amount' => $this->payout->amount,
            ],
        );
    }
}

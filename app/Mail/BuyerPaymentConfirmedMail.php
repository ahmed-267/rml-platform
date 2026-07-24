<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BuyerPaymentConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'RML payment confirmed — '.$this->payment->payment_reference);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.buyer-payment-confirmed',
            with: [
                'payment' => $this->payment,
                'reference' => $this->payment->payment_reference,
                'amount' => $this->payment->amount,
            ],
        );
    }
}

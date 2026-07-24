<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BuyerPaymentCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('rml.mail.payment_created_subject', [
            'reference' => $this->payment->payment_reference,
        ], 'en'));
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.buyer-payment-created',
            with: [
                'payment' => $this->payment,
                'amount' => $this->payment->amount,
                'reference' => $this->payment->payment_reference,
                'method' => $this->payment->method?->value,
            ],
        );
    }
}

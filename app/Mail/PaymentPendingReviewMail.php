<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentPendingReviewMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'RML payment pending review — '.$this->payment->payment_reference);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-pending-review',
            with: [
                'payment' => $this->payment,
                'reference' => $this->payment->payment_reference,
                'amount' => $this->payment->amount,
                'adminUrl' => url('/admin/payments'),
            ],
        );
    }
}

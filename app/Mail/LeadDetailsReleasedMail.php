<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadDetailsReleasedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'RML lead details released — '.$this->payment->payment_reference);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.lead-details-released',
            with: [
                'payment' => $this->payment,
                'reference' => $this->payment->payment_reference,
                'purchasesUrl' => url('/buyer/purchases'),
            ],
        );
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePaymentAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentAccountChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Venue $venue,
        public readonly VenuePaymentAccount $account,
        public readonly User $actor,
        public readonly string $eventLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->venue->name}] Pemberitahuan Perubahan Rekening/QRIS Tujuan",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-account-changed',
        );
    }
}

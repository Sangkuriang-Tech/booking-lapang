<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Scopes\VenueScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentActionRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $bookingId,
        public readonly string $actionType,
        public readonly string $reason,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->loadBooking();
        $timezone = $booking->venue->timezone ?: 'Asia/Jakarta';

        if ($this->actionType === 'reupload') {
            $deadline = $booking->hold_expires_at?->setTimezone($timezone)->format('d M Y, H.i').' WIB';

            return (new MailMessage)
                ->subject("Unggah Ulang Bukti Pembayaran — {$booking->venue->name} ({$booking->code})")
                ->greeting("Halo {$booking->customer_name},")
                ->line("Staf {$booking->venue->name} meminta Anda mengunggah ulang bukti pembayaran untuk booking **{$booking->code}** ({$booking->court->name}).")
                ->line("**Alasan:** {$this->reason}")
                ->line("**Batas waktu unggah ulang:** {$deadline}")
                ->action('Unggah Bukti Baru', url('/bookings/'.$booking->ulid));
        }

        return (new MailMessage)
            ->subject("Pembayaran Ditolak — {$booking->venue->name} ({$booking->code})")
            ->greeting("Halo {$booking->customer_name},")
            ->line("Maaf, pembayaran untuk booking **{$booking->code}** di **{$booking->venue->name}** ({$booking->court->name}) ditolak.")
            ->line("**Alasan:** {$this->reason}")
            ->line('Slot telah dilepas kembali. Anda dapat membuat booking baru melalui halaman venue.')
            ->action('Lihat Detail Booking', url('/bookings/'.$booking->ulid));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $booking = $this->loadBooking();

        return [
            'booking_ulid' => $booking->ulid,
            'booking_code' => $booking->code,
            'venue_name' => $booking->venue->name,
            'action_type' => $this->actionType,
            'reason' => $this->reason,
            'message' => $this->actionType === 'reupload'
                ? "Bukti pembayaran booking {$booking->code} di {$booking->venue->name} perlu diunggah ulang."
                : "Pembayaran booking {$booking->code} di {$booking->venue->name} ditolak.",
        ];
    }

    private function loadBooking(): Booking
    {
        return Booking::query()
            ->withoutGlobalScopes()
            ->with([
                'venue:id,ulid,name,timezone',
                'court' => static fn ($q) => $q->withoutGlobalScope(VenueScope::class)->select('id', 'venue_id', 'name'),
            ])
            ->findOrFail($this->bookingId);
    }
}

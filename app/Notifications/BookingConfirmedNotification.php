<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Scopes\VenueScope;
use App\Support\QrCodeSvg;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class BookingConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $bookingId,
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
        $startLocal = $booking->starts_at->setTimezone($timezone);
        $endLocal = $booking->ends_at->setTimezone($timezone);

        $remaining = max(0, $booking->price_total - $booking->paid_total);
        $paymentStatus = $remaining === 0
            ? 'Lunas'
            : 'DP (Sisa bayar di tempat: Rp'.number_format($remaining, 0, ',', '.').')';

        $mail = (new MailMessage)
            ->subject("Booking Dikonfirmasi — {$booking->venue->name} ({$booking->code})")
            ->greeting("Halo {$booking->customer_name},")
            ->line("Pembayaran Anda di **{$booking->venue->name}** telah dikonfirmasi.")
            ->line("**Kode Booking:** {$booking->code}")
            ->line("**Lapangan:** {$booking->court->name}")
            ->line('**Jadwal:** '.$startLocal->format('d M Y, H.i').'–'.$endLocal->format('H.i').' WIB')
            ->line('**Total:** Rp'.number_format($booking->price_total, 0, ',', '.'))
            ->line("**Status Pembayaran:** {$paymentStatus}");

        if ($booking->qr_token_enc !== null) {
            $qrDataUri = QrCodeSvg::toDataUri($booking->qr_token_enc);
            $mail->line(new HtmlString(
                '<div style="margin:16px 0;text-align:center;">'
                .'<img src="'.$qrDataUri.'" alt="QR Check-in '.$booking->code.'" width="200" height="200" style="display:inline-block;border:1px solid #DAD8CE;border-radius:10px;padding:8px;background:#FFFFFF;" />'
                .'<p style="font-size:12px;color:#53625B;margin-top:8px;">Tunjukkan QR ini ke staf venue saat check-in (berlaku 60 menit sebelum jadwal).</p>'
                .'</div>'
            ));
        }

        return $mail->action('Lihat Tiket Booking', url('/bookings/'.$booking->ulid));
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
            'court_name' => $booking->court->name,
            'starts_at' => $booking->starts_at->toIso8601String(),
            'ends_at' => $booking->ends_at->toIso8601String(),
            'status' => $booking->status,
            'message' => "Booking {$booking->code} di {$booking->venue->name} telah dikonfirmasi.",
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

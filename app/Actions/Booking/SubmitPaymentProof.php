<?php

namespace App\Actions\Booking;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Payment;
use App\Models\User;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitPaymentProof
{
    public const MAX_FILE_BYTES = 2_097_152; // 2 MB

    public const MAX_DIMENSION_PX = 4000;

    public const MAX_PROOFS_PER_BOOKING = 5;

    /** @var list<string> */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png'];

    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{booking: Booking, payment: Payment, duplicate_warning: bool}
     */
    public function execute(User $customer, Booking $booking, UploadedFile $file, array $data = []): array
    {
        if ($booking->customer_id !== $customer->id) {
            throw ValidationException::withMessages([
                'booking' => ['Anda tidak memiliki akses ke pesanan ini.'],
            ]);
        }

        if (! in_array($booking->status, [Booking::STATUS_HELD, Booking::STATUS_AWAITING_REUPLOAD], true)) {
            throw ValidationException::withMessages([
                'booking' => ['Status pesanan saat ini tidak menerima unggahan bukti pembayaran.'],
            ]);
        }

        if ($booking->hold_expires_at !== null && $booking->hold_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'booking' => ['Batas waktu penahanan slot sudah berakhir.'],
            ]);
        }

        $existingProofCount = Payment::query()
            ->withoutGlobalScopes()
            ->where('booking_id', $booking->id)
            ->count();

        if ($existingProofCount >= self::MAX_PROOFS_PER_BOOKING) {
            throw ValidationException::withMessages([
                'proof' => ['Batas maksimal unggah bukti pembayaran untuk pesanan ini telah tercapai.'],
            ]);
        }

        $processed = $this->validateAndReencodeImage($customer, $booking, $file);

        $sha256 = hash('sha256', $processed['bytes']);
        $isDuplicateProof = Payment::query()
            ->withoutGlobalScopes()
            ->where('proof_sha256', $sha256)
            ->where('booking_id', '!=', $booking->id)
            ->exists();

        $storagePath = 'proofs/'.Str::ulid().'.'.$processed['extension'].'.enc';
        $encryptedPayload = Crypt::encrypt($processed['bytes']);
        Storage::disk('local')->put($storagePath, $encryptedPayload);

        $method = ($data['method'] ?? Payment::METHOD_TRANSFER) === Payment::METHOD_QRIS
            ? Payment::METHOD_QRIS
            : Payment::METHOD_TRANSFER;

        $userNote = isset($data['note']) && is_string($data['note']) && trim($data['note']) !== ''
            ? trim($data['note'])
            : null;

        $paymentNote = $isDuplicateProof
            ? trim(($userNote !== null ? $userNote.' | ' : '').'Peringatan sistem: hash bukti pembayaran sama dengan transaksi lain.')
            : $userNote;

        $result = DB::transaction(function () use (
            $customer,
            $booking,
            $method,
            $storagePath,
            $sha256,
            $processed,
            $paymentNote,
        ): array {
            $lockedBooking = Booking::query()
                ->withoutGlobalScopes()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($lockedBooking->status, [Booking::STATUS_HELD, Booking::STATUS_AWAITING_REUPLOAD], true)) {
                throw ValidationException::withMessages([
                    'booking' => ['Status pesanan sudah berubah dan tidak dapat menerima bukti pembayaran.'],
                ]);
            }

            $fromStatus = $lockedBooking->status;
            $kind = $lockedBooking->payment_mode === Booking::PAYMENT_MODE_FULL
                ? Payment::KIND_FULL
                : Payment::KIND_DP;

            $amount = $lockedBooking->payment_mode === Booking::PAYMENT_MODE_FULL
                ? $lockedBooking->price_total
                : $lockedBooking->dp_required;

            $payment = Payment::query()->create([
                'booking_id' => $lockedBooking->id,
                'venue_id' => $lockedBooking->venue_id,
                'kind' => $kind,
                'method' => $method,
                'amount' => $amount,
                'status' => Payment::STATUS_PENDING,
                'proof_path' => $storagePath,
                'proof_sha256' => $sha256,
                'proof_mime' => $processed['mime'],
                'proof_size' => strlen($processed['bytes']),
                'submitted_at' => now(),
                'note' => $paymentNote,
            ]);

            $lockedBooking->update([
                'status' => Booking::STATUS_AWAITING_VALIDATION,
                'hold_expires_at' => null,
            ]);

            BookingStatusLog::query()->create([
                'booking_id' => $lockedBooking->id,
                'from_status' => $fromStatus,
                'to_status' => Booking::STATUS_AWAITING_VALIDATION,
                'actor_id' => $customer->id,
                'actor_kind' => 'customer',
                'created_at' => now(),
            ]);

            return [
                'booking' => $lockedBooking->fresh(),
                'payment' => $payment,
            ];
        });

        $this->recordAuditLog->execute(
            action: 'payment_proof.submitted',
            actor: $customer,
            venueId: $booking->venue_id,
            subject: $result['payment'],
            newValues: [
                'booking_ulid' => $booking->ulid,
                'method' => $method,
                'proof_mime' => $processed['mime'],
                'proof_sha256' => $sha256,
                'duplicate_hash_detected' => $isDuplicateProof,
            ],
        );

        return [
            'booking' => $result['booking'],
            'payment' => $result['payment'],
            'duplicate_warning' => $isDuplicateProof,
        ];
    }

    /**
     * @return array{bytes: string, mime: string, extension: string}
     */
    private function validateAndReencodeImage(User $customer, Booking $booking, UploadedFile $file): array
    {
        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_file($realPath)) {
            $this->rejectUpload($customer, $booking, 'unreadable_file', 'File bukti pembayaran tidak dapat dibaca.');
        }

        $fileSize = filesize($realPath);
        if ($fileSize === false || $fileSize <= 0 || $fileSize > self::MAX_FILE_BYTES) {
            $this->rejectUpload($customer, $booking, 'file_too_large', 'Bukti bayar maksimal 2 MB (JPG atau PNG).');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($realPath);

        if (! is_string($detectedMime) || ! in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            $this->rejectUpload($customer, $booking, 'invalid_mime:'.(string) $detectedMime, 'Format file tidak valid. Hanya JPG atau PNG yang diizinkan.');
        }

        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false) {
            $this->rejectUpload($customer, $booking, 'invalid_image_header', 'File bukan gambar JPG atau PNG yang valid.');
        }

        [$width, $height, $imageType] = $imageInfo;
        if (
            $width < 1
            || $height < 1
            || $width > self::MAX_DIMENSION_PX
            || $height > self::MAX_DIMENSION_PX
        ) {
            $this->rejectUpload($customer, $booking, "dimensions_exceeded:{$width}x{$height}", 'Dimensi gambar maksimal 4000x4000 piksel.');
        }

        if ($detectedMime === 'image/jpeg' && $imageType === IMAGETYPE_JPEG) {
            $gdImage = @imagecreatefromjpeg($realPath);
            $extension = 'jpg';
        } elseif ($detectedMime === 'image/png' && $imageType === IMAGETYPE_PNG) {
            $gdImage = @imagecreatefrompng($realPath);
            $extension = 'png';
        } else {
            $this->rejectUpload($customer, $booking, 'mime_type_mismatch', 'Format gambar tidak konsisten dengan isi file.');
        }

        if ($gdImage === false) {
            $this->rejectUpload($customer, $booking, 'gd_decode_failed', 'Gambar rusak atau tidak dapat diproses.');
        }

        // Re-encode into a fresh image buffer to strip all EXIF/GPS metadata and embedded payloads.
        ob_start();
        if ($extension === 'jpg') {
            $encoded = imagejpeg($gdImage, null, 85);
        } else {
            imagealphablending($gdImage, false);
            imagesavealpha($gdImage, true);
            $encoded = imagepng($gdImage, null, 6);
        }
        $cleanBytes = ob_get_clean();
        imagedestroy($gdImage);

        if (! $encoded || ! is_string($cleanBytes) || $cleanBytes === '') {
            $this->rejectUpload($customer, $booking, 'gd_reencode_failed', 'Gagal memproses ulang gambar bukti pembayaran.');
        }

        return [
            'bytes' => $cleanBytes,
            'mime' => $detectedMime,
            'extension' => $extension,
        ];
    }

    private function rejectUpload(User $customer, Booking $booking, string $reasonCode, string $userMessage): never
    {
        $this->recordAuditLog->execute(
            action: 'payment_proof.rejected_upload',
            actor: $customer,
            venueId: $booking->venue_id,
            subject: $booking,
            newValues: ['reason_code' => $reasonCode],
        );

        throw ValidationException::withMessages([
            'proof' => [$userMessage],
        ]);
    }
}

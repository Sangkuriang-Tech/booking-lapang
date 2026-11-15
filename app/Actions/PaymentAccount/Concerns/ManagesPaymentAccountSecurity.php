<?php

namespace App\Actions\PaymentAccount\Concerns;

use App\Mail\PaymentAccountChangedMail;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePaymentAccount;
use App\Models\VenueUser;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait ManagesPaymentAccountSecurity
{
    private function verifyReauthentication(User $actor, ?string $currentPassword): void
    {
        if (! is_string($currentPassword) || $currentPassword === '' || ! Hash::check($currentPassword, (string) $actor->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password konfirmasi tidak sesuai.'],
            ]);
        }
    }

    private function storeValidatedQrisImage(UploadedFile $file): string
    {
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'qris_image' => ['File QRIS gagal diunggah.'],
            ]);
        }

        $size = $file->getSize();
        if ($size === false || $size <= 0 || $size > 2_097_152) {
            throw ValidationException::withMessages([
                'qris_image' => ['Ukuran gambar QRIS maksimal 2 MB.'],
            ]);
        }

        $realPath = $file->getRealPath();
        $rawBytes = $realPath !== false ? file_get_contents($realPath) : false;
        if (! is_string($rawBytes)) {
            throw ValidationException::withMessages([
                'qris_image' => ['File QRIS tidak dapat dibaca.'],
            ]);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($rawBytes);

        if (! in_array($mime, ['image/jpeg', 'image/png'], true)) {
            throw ValidationException::withMessages([
                'qris_image' => ['Gambar QRIS harus berformat JPG atau PNG yang valid.'],
            ]);
        }

        $info = @getimagesizefromstring($rawBytes);
        if ($info === false || $info[0] <= 0 || $info[1] <= 0 || $info[0] > 4000 || $info[1] > 4000) {
            throw ValidationException::withMessages([
                'qris_image' => ['Dimensi gambar QRIS tidak valid atau melebihi 4000x4000 piksel.'],
            ]);
        }

        $image = @imagecreatefromstring($rawBytes);
        if ($image === false) {
            throw ValidationException::withMessages([
                'qris_image' => ['File QRIS bukan gambar yang valid.'],
            ]);
        }

        try {
            ob_start();
            imagepng($image, null, 6);
            $reencoded = ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        if (! is_string($reencoded) || $reencoded === '') {
            throw ValidationException::withMessages([
                'qris_image' => ['Gagal memproses gambar QRIS.'],
            ]);
        }

        $path = 'qris/'.Str::lower((string) Str::ulid()).'.png';
        Storage::disk('local')->put($path, $reencoded);

        return $path;
    }

    private function notifyOwnersAndSuperadmins(
        Venue $venue,
        VenuePaymentAccount $account,
        User $actor,
        string $eventLabel,
    ): void {
        $ownerIds = VenueUser::query()
            ->where('venue_id', $venue->id)
            ->where('role', VenueUser::ROLE_OWNER)
            ->pluck('user_id')
            ->push($venue->owner_id)
            ->unique()
            ->all();

        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($ownerIds): void {
                $query->whereIn('id', $ownerIds)
                    ->orWhere('role', User::ROLE_SUPERADMIN);
            })
            ->get(['id', 'name', 'email']);

        foreach ($recipients as $recipient) {
            if (! empty($recipient->email)) {
                Mail::to($recipient->email)->send(
                    new PaymentAccountChangedMail($venue, $account, $actor, $eventLabel),
                );
            }
        }
    }
}

<?php

namespace App\Actions\Venue;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Court;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePhoto;
use finfo;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadVenuePhoto
{
    public const MAX_FILE_BYTES = 5_242_880; // 5 MB

    public const MAX_DIMENSION_PX = 4000;

    public const PRIMARY_MAX_WIDTH = 1600;

    public const MEDIUM_MAX_WIDTH = 640;

    /** @var list<string> */
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Venue $venue, UploadedFile $file, array $data = []): VenuePhoto
    {
        $courtId = ! empty($data['court_id']) ? (int) $data['court_id'] : null;

        if ($courtId !== null) {
            $courtExists = Court::withoutGlobalScopes()
                ->where('id', $courtId)
                ->where('venue_id', $venue->id)
                ->whereNull('deleted_at')
                ->exists();

            if (! $courtExists) {
                throw ValidationException::withMessages([
                    'court_id' => ['Lapangan tidak ditemukan di venue ini.'],
                ]);
            }
        }

        $processed = $this->validateAndConvertImages($actor, $venue, $file);

        $baseDir = 'venue-photos/'.Str::lower($venue->ulid);
        $fileId = Str::lower((string) Str::ulid());
        $primaryPath = $baseDir.'/'.$fileId.'.webp';

        $disk = Storage::disk('public');
        $this->ensureNoScriptExecution($disk);

        $disk->put($primaryPath, $processed['primary_webp']);
        $disk->put($baseDir.'/'.$fileId.'-640.webp', $processed['medium_webp']);

        if ($processed['primary_avif'] !== null) {
            $disk->put($baseDir.'/'.$fileId.'.avif', $processed['primary_avif']);
        }

        if ($processed['medium_avif'] !== null) {
            $disk->put($baseDir.'/'.$fileId.'-640.avif', $processed['medium_avif']);
        }

        $isCover = $courtId === null && (bool) ($data['is_cover'] ?? false);

        return DB::transaction(function () use ($actor, $venue, $courtId, $primaryPath, $processed, $data, $isCover): VenuePhoto {
            if ($isCover) {
                VenuePhoto::withoutGlobalScopes()
                    ->where('venue_id', $venue->id)
                    ->whereNull('court_id')
                    ->where('is_cover', true)
                    ->update(['is_cover' => false]);
            }

            $nextOrder = (int) VenuePhoto::withoutGlobalScopes()
                ->where('venue_id', $venue->id)
                ->max('sort_order') + 1;

            $photo = VenuePhoto::query()->create([
                'venue_id' => $venue->id,
                'court_id' => $courtId,
                'path' => $primaryPath,
                'alt_text' => Str::limit(trim((string) ($data['alt_text'] ?? $venue->name)), 160, ''),
                'width' => $processed['width'],
                'height' => $processed['height'],
                'is_cover' => $isCover,
                'sort_order' => (int) ($data['sort_order'] ?? $nextOrder),
            ]);

            $this->recordAuditLog->execute(
                action: 'venue_photo.uploaded',
                actor: $actor,
                venueId: $venue->id,
                subject: $photo,
                newValues: [
                    'path' => $photo->path,
                    'width' => $photo->width,
                    'height' => $photo->height,
                    'is_cover' => $photo->is_cover,
                ],
            );

            return $photo;
        });
    }

    /**
     * @return array{
     *     primary_webp: string,
     *     medium_webp: string,
     *     primary_avif: string|null,
     *     medium_avif: string|null,
     *     width: int,
     *     height: int
     * }
     */
    private function validateAndConvertImages(User $actor, Venue $venue, UploadedFile $file): array
    {
        if (! $file->isValid()) {
            $this->rejectUpload($actor, $venue, 'File foto gagal diunggah.');
        }

        $size = $file->getSize();
        if ($size === false || $size <= 0 || $size > self::MAX_FILE_BYTES) {
            $this->rejectUpload($actor, $venue, 'Ukuran foto maksimal 5 MB.');
        }

        $realPath = $file->getRealPath();
        if ($realPath === false) {
            $this->rejectUpload($actor, $venue, 'File foto tidak dapat dibaca.');
        }

        $rawBytes = file_get_contents($realPath);
        if ($rawBytes === false) {
            $this->rejectUpload($actor, $venue, 'File foto tidak dapat dibaca.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer($rawBytes);

        if (! is_string($detectedMime) || ! in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            $this->rejectUpload($actor, $venue, 'Foto harus berformat JPG, PNG, atau WebP yang valid.');
        }

        $imageInfo = @getimagesizefromstring($rawBytes);
        if ($imageInfo === false || $imageInfo[0] <= 0 || $imageInfo[1] <= 0) {
            $this->rejectUpload($actor, $venue, 'File bukan gambar yang valid.');
        }

        if ($imageInfo[0] > self::MAX_DIMENSION_PX || $imageInfo[1] > self::MAX_DIMENSION_PX) {
            $this->rejectUpload($actor, $venue, 'Dimensi foto maksimal 4000x4000 piksel.');
        }

        $sourceImage = @imagecreatefromstring($rawBytes);
        if ($sourceImage === false) {
            $this->rejectUpload($actor, $venue, 'File gambar rusak atau tidak didukung.');
        }

        try {
            $primary = $this->resizeIfNeeded($sourceImage, self::PRIMARY_MAX_WIDTH);
            $medium = $this->resizeIfNeeded($sourceImage, self::MEDIUM_MAX_WIDTH);

            try {
                $width = imagesx($primary);
                $height = imagesy($primary);

                return [
                    'primary_webp' => $this->encodeWebp($primary, 84),
                    'medium_webp' => $this->encodeWebp($medium, 80),
                    'primary_avif' => $this->encodeAvif($primary, 65),
                    'medium_avif' => $this->encodeAvif($medium, 60),
                    'width' => $width,
                    'height' => $height,
                ];
            } finally {
                if ($primary !== $sourceImage) {
                    imagedestroy($primary);
                }
                if ($medium !== $sourceImage) {
                    imagedestroy($medium);
                }
            }
        } finally {
            imagedestroy($sourceImage);
        }
    }

    private function resizeIfNeeded(GdImage $source, int $maxWidth): GdImage
    {
        $origW = imagesx($source);
        $origH = imagesy($source);

        if ($origW <= $maxWidth) {
            $canvas = imagecreatetruecolor($origW, $origH);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopy($canvas, $source, 0, 0, 0, 0, $origW, $origH);

            return $canvas;
        }

        $targetW = $maxWidth;
        $targetH = max(1, (int) round(($origH / $origW) * $targetW));

        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);

        return $canvas;
    }

    private function encodeWebp(GdImage $image, int $quality): string
    {
        ob_start();
        $ok = imagewebp($image, null, $quality);
        $bytes = ob_get_clean();

        if (! $ok || ! is_string($bytes) || $bytes === '') {
            throw ValidationException::withMessages([
                'photo' => ['Gagal memproses gambar ke format WebP.'],
            ]);
        }

        return $bytes;
    }

    private function encodeAvif(GdImage $image, int $quality): ?string
    {
        if (! function_exists('imageavif')) {
            return null;
        }

        ob_start();
        $ok = @imageavif($image, null, $quality);
        $bytes = ob_get_clean();

        if (! $ok || ! is_string($bytes) || $bytes === '') {
            return null;
        }

        return $bytes;
    }

    private function ensureNoScriptExecution(Filesystem $disk): void
    {
        $htaccessPath = 'venue-photos/.htaccess';
        if ($disk->exists($htaccessPath)) {
            return;
        }

        $rules = implode("\n", [
            'Options -ExecCGI -Indexes',
            '<IfModule mod_php.c>',
            '    php_flag engine off',
            '</IfModule>',
            '<FilesMatch "\.(php|phtml|php[3-8]|phar|cgi|pl|py|sh)$">',
            '    Require all denied',
            '</FilesMatch>',
            '',
        ]);

        $disk->put($htaccessPath, $rules);
    }

    private function rejectUpload(User $actor, Venue $venue, string $message): never
    {
        $this->recordAuditLog->execute(
            action: 'venue_photo.upload_rejected',
            actor: $actor,
            venueId: $venue->id,
            newValues: ['reason' => $message],
        );

        throw ValidationException::withMessages([
            'photo' => [$message],
        ]);
    }
}

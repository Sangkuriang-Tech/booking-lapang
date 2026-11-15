<?php

use App\Actions\Booking\HoldSlot;
use App\Actions\Booking\SubmitPaymentProof;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePaymentAccount;
use App\Models\VenueUser;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Storage::fake('local');

    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

function createValidJpegFile(string $filename = 'proof.jpg', int $width = 120, int $height = 120): UploadedFile
{
    $tmpPath = tempnam(sys_get_temp_dir(), 'jpg_');
    $img = imagecreatetruecolor($width, $height);
    $bg = imagecolorallocate($img, 15, 59, 46);
    imagefill($img, 0, 0, $bg);
    imagejpeg($img, $tmpPath, 90);
    imagedestroy($img);

    return new UploadedFile($tmpPath, $filename, 'image/jpeg', null, true);
}

it('rejects PHP disguised as JPG, SVG, oversized dimensions, and logs rejected uploads', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', 'Mini Soccer A')->firstOrFail();
    $customer = User::query()->where('email', 'customer2@example.test')->firstOrFail();

    $nextWed = CarbonImmutable::now('Asia/Jakarta')->next(CarbonImmutable::WEDNESDAY);
    $booking = app(HoldSlot::class)->execute($customer, $court, $nextWed->setTime(19, 0)->utc(), $nextWed->setTime(20, 0)->utc());

    $submitProof = app(SubmitPaymentProof::class);

    // 1. PHP file disguised as .jpg
    $phpFile = UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo "pwned"; ?>');
    expect(fn () => $submitProof->execute($customer, $booking, $phpFile))
        ->toThrow(ValidationException::class);

    // 2. SVG file
    $svgFile = UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    expect(fn () => $submitProof->execute($customer, $booking, $svgFile))
        ->toThrow(ValidationException::class);

    // 3. Oversized dimensions (> 4000px)
    $bombFile = createValidJpegFile('bomb.jpg', 4050, 100);
    expect(fn () => $submitProof->execute($customer, $booking, $bombFile))
        ->toThrow(ValidationException::class);

    expect(AuditLog::query()->where('action', 'payment_proof.rejected_upload')->count())->toBe(3);
});

it('strips EXIF/GPS metadata, encrypts proof on private disk, and flags duplicate SHA-256 hashes (Threat T3)', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', 'Mini Soccer A')->firstOrFail();
    $customer1 = User::query()->where('email', 'customer1@example.test')->firstOrFail();
    $customer2 = User::query()->where('email', 'customer2@example.test')->firstOrFail();

    $nextThu = CarbonImmutable::now('Asia/Jakarta')->next(CarbonImmutable::THURSDAY);
    $booking1 = app(HoldSlot::class)->execute($customer1, $court, $nextThu->setTime(19, 0)->utc(), $nextThu->setTime(20, 0)->utc());
    $booking2 = app(HoldSlot::class)->execute($customer2, $court, $nextThu->setTime(20, 0)->utc(), $nextThu->setTime(21, 0)->utc());

    // Build a JPEG with fake EXIF APP1 marker containing GPS string
    $validJpeg = createValidJpegFile('with-exif.jpg');
    $rawBytes = file_get_contents($validJpeg->getRealPath());
    $exifSegment = "\xFF\xE1\x00\x1CExif\x00\x00GPS_LAT_-6.200000_LON_106.8";
    $jpegWithExif = substr($rawBytes, 0, 2).$exifSegment.substr($rawBytes, 2);

    $tmpExifPath = tempnam(sys_get_temp_dir(), 'exif_');
    file_put_contents($tmpExifPath, $jpegWithExif);
    $uploadedWithExif = new UploadedFile($tmpExifPath, 'receipt.jpg', 'image/jpeg', null, true);

    $submitProof = app(SubmitPaymentProof::class);
    $result1 = $submitProof->execute($customer1, $booking1, $uploadedWithExif, ['method' => Payment::METHOD_TRANSFER]);

    expect($result1['booking']->status)->toBe(Booking::STATUS_AWAITING_VALIDATION)
        ->and($result1['duplicate_warning'])->toBeFalse();

    $storedEncrypted = Storage::disk('local')->get($result1['payment']->proof_path);
    // Stored file is encrypted (not raw JPEG)
    expect(str_starts_with($storedEncrypted, "\xFF\xD8\xFF"))->toBeFalse();

    // Decrypted bytes are valid JPEG and stripped of EXIF/GPS marker
    $decryptedBytes = Crypt::decrypt($storedEncrypted);
    expect(str_starts_with($decryptedBytes, "\xFF\xD8\xFF"))->toBeTrue()
        ->and(str_contains($decryptedBytes, 'GPS_LAT_-6.200000'))->toBeFalse();

    // Uploading the same image to a second booking triggers duplicate SHA-256 warning (Threat T3)
    $uploadedAgain = new UploadedFile($tmpExifPath, 'receipt-copy.jpg', 'image/jpeg', null, true);
    $result2 = $submitProof->execute($customer2, $booking2, $uploadedAgain, ['method' => Payment::METHOD_TRANSFER]);

    expect($result2['duplicate_warning'])->toBeTrue()
        ->and($result2['payment']->proof_sha256)->toBe($result1['payment']->proof_sha256)
        ->and($result2['payment']->note)->toContain('Peringatan sistem');
});

it('prevents IDOR access to payment proofs and enforces authorized QRIS access', function (): void {
    $venue1 = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $venue2 = Venue::query()->where('slug', 'arena-contoh-dua')->firstOrFail();
    $court1 = Court::withoutGlobalScopes()->where('venue_id', $venue1->id)->where('name', 'Mini Soccer A')->firstOrFail();

    $customer1 = User::query()->where('email', 'customer1@example.test')->firstOrFail();
    $customer2 = User::query()->where('email', 'customer2@example.test')->firstOrFail();
    $staff1 = User::query()->where('email', 'staff1@example.test')->firstOrFail();

    // Create a staff user who only belongs to venue2
    $staffVenue2Only = User::query()->create([
        'role' => User::ROLE_STAFF,
        'name' => 'Staf Venue 2 Saja',
        'email' => 'staff-v2-only@example.test',
        'password' => 'PasswordDev123!',
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    VenueUser::query()->insert([
        'venue_id' => $venue2->id,
        'user_id' => $staffVenue2Only->id,
        'role' => 'staff',
        'created_at' => now(),
    ]);

    $nextFri = CarbonImmutable::now('Asia/Jakarta')->next(CarbonImmutable::FRIDAY);
    $booking = app(HoldSlot::class)->execute($customer1, $court1, $nextFri->setTime(19, 0)->utc(), $nextFri->setTime(20, 0)->utc());
    $proofResult = app(SubmitPaymentProof::class)->execute($customer1, $booking, createValidJpegFile());
    $payment = $proofResult['payment'];

    // Owner customer & venue1 staff can view payment proof
    $this->actingAs($customer1)
        ->get("/payments/{$payment->ulid}/proof")
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($staff1)
        ->withSession(['active_venue_id' => $venue1->id])
        ->get("/payments/{$payment->ulid}/proof")
        ->assertOk();

    // IDOR: other customer and staff of another venue are denied (403)
    $this->actingAs($customer2)
        ->get("/payments/{$payment->ulid}/proof")
        ->assertForbidden();

    $this->actingAs($staffVenue2Only)
        ->withSession(['active_venue_id' => $venue1->id])
        ->get("/payments/{$payment->ulid}/proof")
        ->assertForbidden();

    // QRIS endpoint checks: put dummy QRIS image on local disk
    $qrisAccount = VenuePaymentAccount::query()
        ->withoutGlobalScopes()
        ->where('venue_id', $venue1->id)
        ->where('kind', VenuePaymentAccount::KIND_QRIS)
        ->firstOrFail();

    $dummyPng = file_get_contents(createValidJpegFile('qris.jpg')->getRealPath());
    Storage::disk('local')->put($qrisAccount->qris_image_path, $dummyPng);

    // customer1 has active booking at venue1 -> allowed
    $this->actingAs($customer1)
        ->get("/venues/{$venue1->ulid}/payment-accounts/{$qrisAccount->id}/qris")
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    // Fresh customer without active booking at venue1 -> forbidden
    $outsider = User::query()->create([
        'role' => User::ROLE_CUSTOMER,
        'name' => 'Outsider',
        'email' => 'outsider@example.test',
        'google_id' => 'google-outsider',
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $this->actingAs($outsider)
        ->get("/venues/{$venue1->ulid}/payment-accounts/{$qrisAccount->id}/qris")
        ->assertForbidden();

    // Future visible_from QRIS -> forbidden
    $qrisAccount->update(['visible_from' => now()->addHours(12)]);
    $this->actingAs($customer1)
        ->get("/venues/{$venue1->ulid}/payment-accounts/{$qrisAccount->id}/qris")
        ->assertForbidden();
});

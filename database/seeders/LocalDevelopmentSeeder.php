<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Court;
use App\Models\CourtCategory;
use App\Models\OperatingHour;
use App\Models\Payment;
use App\Models\PriceRule;
use App\Models\Sport;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePaymentAccount;
use App\Models\VenueUser;
use App\Support\VenueContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LocalDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'staging', 'testing'])) {
            return;
        }

        app(VenueContext::class)->withoutScope(function (): void {
            $this->seedData();
        });
    }

    private function seedData(): void
    {
        $password = Hash::make('PasswordDev123!');

        $superadmin = User::query()->create([
            'role' => User::ROLE_SUPERADMIN,
            'name' => 'Superadmin Contoh',
            'email' => 'superadmin@example.test',
            'password' => $password,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $owner = User::query()->create([
            'role' => User::ROLE_STAFF,
            'name' => 'Pemilik Utama',
            'email' => 'owner@example.test',
            'phone' => '6281234567890',
            'password' => $password,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $ownerGrace = User::query()->create([
            'role' => User::ROLE_STAFF,
            'name' => 'Pemilik Masa Tenggang',
            'email' => 'owner_grace@example.test',
            'phone' => '6281234567891',
            'password' => $password,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $ownerExpired = User::query()->create([
            'role' => User::ROLE_STAFF,
            'name' => 'Pemilik Nonaktif',
            'email' => 'owner_expired@example.test',
            'phone' => '6281234567892',
            'password' => $password,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $staff1 = User::query()->create([
            'role' => User::ROLE_STAFF,
            'name' => 'Karyawan Satu',
            'email' => 'staff1@example.test',
            'password' => $password,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $staff2 = User::query()->create([
            'role' => User::ROLE_STAFF,
            'name' => 'Karyawan Dua',
            'email' => 'staff2@example.test',
            'password' => $password,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $customer1 = User::query()->create([
            'role' => User::ROLE_CUSTOMER,
            'name' => 'Pembooking Satu',
            'email' => 'customer1@example.test',
            'phone' => '6281298765431',
            'google_id' => 'google-dummy-customer-1',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $customer2 = User::query()->create([
            'role' => User::ROLE_CUSTOMER,
            'name' => 'Pembooking Dua',
            'email' => 'customer2@example.test',
            'phone' => '6281298765432',
            'google_id' => 'google-dummy-customer-2',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        $starterPlan = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();
        $proPlan = SubscriptionPlan::query()->where('code', 'pro')->firstOrFail();

        $today = CarbonImmutable::now('Asia/Jakarta')->startOfDay();

        $this->createSubscription($owner, $proPlan, $today->subDays(5), $today->addMonths(12), $superadmin);
        $this->createSubscription($ownerGrace, $starterPlan, $today->subDays(33), $today->subDays(3), $superadmin);
        $this->createSubscription($ownerExpired, $starterPlan, $today->subDays(40), $today->subDays(10), $superadmin);

        $sports = Sport::query()->pluck('id', 'slug');

        // Venue 1: Antic Futsal Center (contoh)
        $venue1 = Venue::query()->create([
            'owner_id' => $owner->id,
            'slug' => 'antic-futsal-center-contoh',
            'name' => 'Antic Futsal Center (contoh)',
            'description' => 'Venue contoh untuk pengembangan lokal dan pengujian.',
            'city' => 'Kota Contoh',
            'city_slug' => 'kota-contoh',
            'address' => 'Jl. Contoh Olahraga No. 1',
            'timezone' => 'Asia/Jakarta',
            'hold_minutes' => 20,
            'dp_percent' => 30,
            'cancel_min_hours' => 24,
            'reupload_minutes' => 60,
            'is_published' => true,
        ]);

        VenueUser::query()->insert([
            ['venue_id' => $venue1->id, 'user_id' => $owner->id, 'role' => VenueUser::ROLE_OWNER, 'created_at' => now()],
            ['venue_id' => $venue1->id, 'user_id' => $staff1->id, 'role' => VenueUser::ROLE_STAFF, 'created_at' => now()],
            ['venue_id' => $venue1->id, 'user_id' => $staff2->id, 'role' => VenueUser::ROLE_STAFF, 'created_at' => now()],
        ]);

        $indoor1 = CourtCategory::query()->create(['venue_id' => $venue1->id, 'name' => 'Indoor']);
        $outdoor1 = CourtCategory::query()->create(['venue_id' => $venue1->id, 'name' => 'Outdoor']);

        $v1CourtsConfig = [
            ['name' => 'Mini Soccer A', 'sport' => 'mini-soccer', 'category' => $outdoor1->id, 'wd_day' => 250000, 'wd_night' => 350000, 'we_day' => 300000, 'we_night' => 400000],
            ['name' => 'Mini Soccer B', 'sport' => 'mini-soccer', 'category' => $outdoor1->id, 'wd_day' => 220000, 'wd_night' => 320000, 'we_day' => 270000, 'we_night' => 370000],
            ['name' => 'Futsal 1', 'sport' => 'futsal', 'category' => $indoor1->id, 'wd_day' => 120000, 'wd_night' => 180000, 'we_day' => 150000, 'we_night' => 200000],
            ['name' => 'Padel 1', 'sport' => 'padel', 'category' => $indoor1->id, 'wd_day' => 150000, 'wd_night' => 250000, 'we_day' => 200000, 'we_night' => 300000],
            ['name' => 'Basket 1', 'sport' => 'basket', 'category' => $indoor1->id, 'wd_day' => 100000, 'wd_night' => 150000, 'we_day' => 130000, 'we_night' => 180000],
        ];

        $v1Courts = [];
        foreach ($v1CourtsConfig as $index => $cfg) {
            $court = Court::query()->create([
                'venue_id' => $venue1->id,
                'sport_id' => $sports[$cfg['sport']],
                'category_id' => $cfg['category'],
                'name' => $cfg['name'],
                'is_active' => true,
                'sort_order' => $index + 1,
            ]);
            $v1Courts[$cfg['name']] = $court;

            PriceRule::query()->insert([
                ['court_id' => $court->id, 'day_type' => 'weekday', 'start_min' => 480, 'end_min' => 1020, 'price_per_hour' => $cfg['wd_day'], 'created_at' => now(), 'updated_at' => now()],
                ['court_id' => $court->id, 'day_type' => 'weekday', 'start_min' => 1020, 'end_min' => 1380, 'price_per_hour' => $cfg['wd_night'], 'created_at' => now(), 'updated_at' => now()],
                ['court_id' => $court->id, 'day_type' => 'weekend', 'start_min' => 420, 'end_min' => 1020, 'price_per_hour' => $cfg['we_day'], 'created_at' => now(), 'updated_at' => now()],
                ['court_id' => $court->id, 'day_type' => 'weekend', 'start_min' => 1020, 'end_min' => 1500, 'price_per_hour' => $cfg['we_night'], 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        foreach (range(0, 6) as $weekday) {
            $isWeekend = in_array($weekday, [0, 6], true);
            OperatingHour::query()->create([
                'venue_id' => $venue1->id,
                'weekday' => $weekday,
                'opens_min' => $isWeekend ? 420 : 480,
                'closes_min' => $isWeekend ? 1500 : 1380,
                'is_closed' => false,
            ]);
        }

        VenuePaymentAccount::query()->create([
            'venue_id' => $venue1->id,
            'kind' => VenuePaymentAccount::KIND_BANK,
            'bank_name' => 'Bank Contoh',
            'account_number' => '0000000000',
            'account_holder' => 'Antic Futsal Center (contoh)',
            'is_active' => true,
            'visible_from' => now()->subDay(),
            'created_by' => $owner->id,
        ]);

        VenuePaymentAccount::query()->create([
            'venue_id' => $venue1->id,
            'kind' => VenuePaymentAccount::KIND_QRIS,
            'qris_image_path' => 'qris/placeholder-venue-1.png',
            'is_active' => true,
            'visible_from' => now()->subDay(),
            'created_by' => $owner->id,
        ]);

        // Venue 2: Arena Contoh Dua (Asia/Makassar, unpublished)
        $venue2 = Venue::query()->create([
            'owner_id' => $owner->id,
            'slug' => 'arena-contoh-dua',
            'name' => 'Arena Contoh Dua',
            'city' => 'Kota Contoh',
            'city_slug' => 'kota-contoh',
            'address' => 'Jl. Contoh Timur No. 2',
            'timezone' => 'Asia/Makassar',
            'is_published' => false,
        ]);

        VenueUser::query()->insert([
            ['venue_id' => $venue2->id, 'user_id' => $owner->id, 'role' => VenueUser::ROLE_OWNER, 'created_at' => now()],
            ['venue_id' => $venue2->id, 'user_id' => $staff2->id, 'role' => VenueUser::ROLE_STAFF, 'created_at' => now()],
        ]);

        $indoor2 = CourtCategory::query()->create(['venue_id' => $venue2->id, 'name' => 'Indoor']);
        $futsal2 = Court::query()->create(['venue_id' => $venue2->id, 'sport_id' => $sports['futsal'], 'category_id' => $indoor2->id, 'name' => 'Futsal Utama', 'sort_order' => 1]);
        $badminton2 = Court::query()->create(['venue_id' => $venue2->id, 'sport_id' => $sports['badminton'], 'category_id' => $indoor2->id, 'name' => 'Badminton 1', 'sort_order' => 2]);

        PriceRule::query()->insert([
            ['court_id' => $futsal2->id, 'day_type' => 'weekday', 'start_min' => 540, 'end_min' => 1320, 'price_per_hour' => 130000, 'created_at' => now(), 'updated_at' => now()],
            ['court_id' => $futsal2->id, 'day_type' => 'weekend', 'start_min' => 540, 'end_min' => 1320, 'price_per_hour' => 190000, 'created_at' => now(), 'updated_at' => now()],
            ['court_id' => $badminton2->id, 'day_type' => 'weekday', 'start_min' => 540, 'end_min' => 1320, 'price_per_hour' => 60000, 'created_at' => now(), 'updated_at' => now()],
            ['court_id' => $badminton2->id, 'day_type' => 'weekend', 'start_min' => 540, 'end_min' => 1320, 'price_per_hour' => 80000, 'created_at' => now(), 'updated_at' => now()],
        ]);

        foreach (range(0, 6) as $weekday) {
            OperatingHour::query()->create([
                'venue_id' => $venue2->id,
                'weekday' => $weekday,
                'opens_min' => 540,
                'closes_min' => 1320,
                'is_closed' => false,
            ]);
        }

        // Venue 3: Grace period (H+3)
        $venueGrace = Venue::query()->create([
            'owner_id' => $ownerGrace->id,
            'slug' => 'venue-masa-tenggang-contoh',
            'name' => 'Venue Masa Tenggang (contoh)',
            'city' => 'Kota Contoh',
            'city_slug' => 'kota-contoh',
            'address' => 'Jl. Contoh Tenggang No. 3',
            'timezone' => 'Asia/Jakarta',
            'is_published' => true,
        ]);
        VenueUser::query()->create(['venue_id' => $venueGrace->id, 'user_id' => $ownerGrace->id, 'role' => VenueUser::ROLE_OWNER, 'created_at' => now()]);
        Court::query()->create(['venue_id' => $venueGrace->id, 'sport_id' => $sports['futsal'], 'name' => 'Futsal Grace', 'sort_order' => 1]);

        // Venue 4: Expired (> H+7)
        $venueExpired = Venue::query()->create([
            'owner_id' => $ownerExpired->id,
            'slug' => 'venue-nonaktif-contoh',
            'name' => 'Venue Nonaktif (contoh)',
            'city' => 'Kota Contoh',
            'city_slug' => 'kota-contoh',
            'address' => 'Jl. Contoh Nonaktif No. 4',
            'timezone' => 'Asia/Jakarta',
            'is_published' => true,
        ]);
        VenueUser::query()->create(['venue_id' => $venueExpired->id, 'user_id' => $ownerExpired->id, 'role' => VenueUser::ROLE_OWNER, 'created_at' => now()]);
        Court::query()->create(['venue_id' => $venueExpired->id, 'sport_id' => $sports['futsal'], 'name' => 'Futsal Nonaktif', 'sort_order' => 1]);

        // Bookings in Venue 1 (all 11 statuses + OTS; tomorrow 21:00 on Mini Soccer A left free)
        $jakartaNow = CarbonImmutable::now('Asia/Jakarta');
        $currentHourStart = $jakartaNow->startOfHour();

        $this->seedBooking($venue1, $v1Courts['Mini Soccer A'], $customer1, Booking::STATUS_HELD, $jakartaNow->addDay()->setTime(19, 0), 350000, Booking::PAYMENT_MODE_DP, 0, [
            'hold_expires_at' => now()->addMinutes(20),
        ]);
        $this->seedBooking($venue1, $v1Courts['Mini Soccer B'], $customer2, Booking::STATUS_AWAITING_VALIDATION, $jakartaNow->addDay()->setTime(20, 0), 320000, Booking::PAYMENT_MODE_DP, 0, [
            'with_pending_payment' => true,
        ]);
        $this->seedBooking($venue1, $v1Courts['Futsal 1'], $customer1, Booking::STATUS_AWAITING_REUPLOAD, $jakartaNow->addDay()->setTime(18, 0), 180000, Booking::PAYMENT_MODE_FULL, 0, [
            'hold_expires_at' => now()->addMinutes(60),
            'status_reason' => 'Bukti buram',
        ]);
        $this->seedBooking($venue1, $v1Courts['Padel 1'], $customer1, Booking::STATUS_CONFIRMED, $jakartaNow->addDays(2)->setTime(19, 0), 250000, Booking::PAYMENT_MODE_DP, 75000, [
            'with_qr' => true,
        ]);
        $this->seedBooking($venue1, $v1Courts['Basket 1'], $customer2, Booking::STATUS_CONFIRMED, $jakartaNow->addDays(2)->setTime(20, 0), 150000, Booking::PAYMENT_MODE_FULL, 150000, [
            'with_qr' => true,
        ]);
        $this->seedBooking($venue1, $v1Courts['Mini Soccer A'], $customer1, Booking::STATUS_CHECKED_IN, $currentHourStart, 350000, Booking::PAYMENT_MODE_FULL, 350000, [
            'with_qr' => true,
            'qr_used' => true,
        ]);
        $this->seedBooking($venue1, $v1Courts['Futsal 1'], $customer2, Booking::STATUS_COMPLETED, $jakartaNow->subDay()->setTime(19, 0), 180000, Booking::PAYMENT_MODE_FULL, 180000);
        $this->seedBooking($venue1, $v1Courts['Padel 1'], $customer1, Booking::STATUS_REJECTED, $jakartaNow->subDay()->setTime(20, 0), 250000, Booking::PAYMENT_MODE_DP, 0, [
            'status_reason' => 'Bukti tidak sah',
        ]);
        $this->seedBooking($venue1, $v1Courts['Basket 1'], $customer2, Booking::STATUS_EXPIRED, $jakartaNow->subDay()->setTime(18, 0), 150000, Booking::PAYMENT_MODE_DP, 0);
        $this->seedBooking($venue1, $v1Courts['Mini Soccer B'], $customer1, Booking::STATUS_CANCELLED, $jakartaNow->subDays(2)->setTime(19, 0), 320000, Booking::PAYMENT_MODE_DP, 0);
        $this->seedBooking($venue1, $v1Courts['Mini Soccer A'], $customer2, Booking::STATUS_NO_SHOW, $jakartaNow->subDays(2)->setTime(20, 0), 350000, Booking::PAYMENT_MODE_DP, 105000);

        // OTS booking (confirmed, cash)
        $this->seedBooking($venue1, $v1Courts['Mini Soccer B'], null, Booking::STATUS_CONFIRMED, $jakartaNow->addDays(2)->setTime(19, 0), 320000, Booking::PAYMENT_MODE_FULL, 320000, [
            'source' => Booking::SOURCE_OTS,
            'created_by' => $staff1->id,
            'customer_name' => 'Tamu Walk-In Contoh',
        ]);
    }

    private function createSubscription(
        User $owner,
        SubscriptionPlan $plan,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        User $creator,
    ): Subscription {
        return Subscription::query()->create([
            'owner_id' => $owner->id,
            'plan_id' => $plan->id,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
            'is_active' => true,
            'max_venues' => $plan->max_venues,
            'max_courts_per_venue' => $plan->max_courts_per_venue,
            'max_staff_total' => $plan->max_staff_total,
            'max_payment_accounts_per_venue' => $plan->max_payment_accounts_per_venue,
            'proof_retention_days' => $plan->proof_retention_days,
            'can_export_reports' => $plan->can_export_reports,
            'created_by' => $creator->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function seedBooking(
        Venue $venue,
        Court $court,
        ?User $customer,
        string $status,
        CarbonImmutable $startsAt,
        int $priceTotal,
        string $paymentMode,
        int $paidTotal,
        array $options = [],
    ): Booking {
        $endsAt = $startsAt->addHour();
        $dpRequired = $paymentMode === Booking::PAYMENT_MODE_FULL
            ? $priceTotal
            : (int) ceil($priceTotal * ($venue->dp_percent / 100));

        $qrToken = ! empty($options['with_qr']) ? bin2hex(random_bytes(32)) : null;

        $booking = Booking::query()->create([
            'code' => Str::upper(Str::random(8)),
            'venue_id' => $venue->id,
            'court_id' => $court->id,
            'customer_id' => $customer?->id,
            'customer_name' => $options['customer_name'] ?? $customer?->name ?? 'Tamu OTS',
            'customer_email' => $customer?->email,
            'customer_phone' => $customer?->phone ?? '6281200000000',
            'source' => $options['source'] ?? Booking::SOURCE_ONLINE,
            'status' => $status,
            'starts_at' => $startsAt->utc(),
            'ends_at' => $endsAt->utc(),
            'play_date' => $startsAt->toDateString(),
            'price_total' => $priceTotal,
            'payment_mode' => $paymentMode,
            'dp_required' => $dpRequired,
            'paid_total' => $paidTotal,
            'hold_expires_at' => $options['hold_expires_at'] ?? null,
            'qr_token_hash' => $qrToken !== null ? hash('sha256', $qrToken) : null,
            'qr_token_enc' => $qrToken,
            'qr_valid_from' => $qrToken !== null ? $startsAt->subMinutes(60)->utc() : null,
            'qr_valid_until' => $qrToken !== null ? $endsAt->utc() : null,
            'qr_used_at' => ! empty($options['qr_used']) ? now() : null,
            'confirmed_at' => in_array($status, [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN, Booking::STATUS_COMPLETED], true) ? now() : null,
            'checked_in_at' => in_array($status, [Booking::STATUS_CHECKED_IN, Booking::STATUS_COMPLETED], true) ? now() : null,
            'completed_at' => $status === Booking::STATUS_COMPLETED ? now() : null,
            'cancelled_at' => $status === Booking::STATUS_CANCELLED ? now() : null,
            'status_reason' => $options['status_reason'] ?? null,
            'created_by' => $options['created_by'] ?? $customer?->id,
        ]);

        BookingStatusLog::query()->create([
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => $status,
            'actor_id' => $options['created_by'] ?? $customer?->id,
            'actor_kind' => ($options['source'] ?? Booking::SOURCE_ONLINE) === Booking::SOURCE_OTS ? 'staff' : 'customer',
            'reason' => $options['status_reason'] ?? null,
            'created_at' => now(),
        ]);

        if (! empty($options['with_pending_payment'])) {
            Payment::query()->create([
                'booking_id' => $booking->id,
                'venue_id' => $venue->id,
                'kind' => Payment::KIND_DP,
                'method' => Payment::METHOD_TRANSFER,
                'amount' => $dpRequired,
                'status' => Payment::STATUS_PENDING,
                'proof_path' => 'proofs/dummy-proof.jpg',
                'proof_sha256' => hash('sha256', 'dummy-proof-'.$booking->id),
                'proof_mime' => 'image/jpeg',
                'proof_size' => 102400,
                'submitted_at' => now(),
            ]);
        }

        return $booking;
    }
}

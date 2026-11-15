<?php

use App\Models\Booking;
use App\Models\User;
use App\Models\VenuePaymentAccount;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('stores sensitive phone, account_number, and qr_token_enc encrypted in database while keeping name and email searchable', function (): void {
    $customer = User::query()->where('email', 'customer1@example.test')->firstOrFail();
    $rawUser = DB::table('users')->where('id', $customer->id)->first();

    expect($customer->phone)->toBe('6281298765431');
    expect($rawUser->phone)->not->toBe('6281298765431');
    expect($rawUser->name)->toBe('Pembooking Satu');
    expect($rawUser->email)->toBe('customer1@example.test');

    $paymentAccount = VenuePaymentAccount::withoutGlobalScopes()
        ->where('kind', VenuePaymentAccount::KIND_BANK)
        ->firstOrFail();
    $rawAccount = DB::table('venue_payment_accounts')->where('id', $paymentAccount->id)->first();

    expect($paymentAccount->account_number)->toBe('0000000000');
    expect($rawAccount->account_number)->not->toBe('0000000000');

    $bookingWithQr = Booking::withoutGlobalScopes()
        ->whereNotNull('qr_token_enc')
        ->firstOrFail();
    $rawBooking = DB::table('bookings')->where('id', $bookingWithQr->id)->first();

    expect($bookingWithQr->customer_phone)->toBe('6281298765431');
    expect($rawBooking->customer_phone)->not->toBe('6281298765431');
    expect(strlen((string) $bookingWithQr->qr_token_enc))->toBe(64);
    expect($rawBooking->qr_token_enc)->not->toBe($bookingWithQr->qr_token_enc);
    expect($rawBooking->qr_token_hash)->toBe(hash('sha256', (string) $bookingWithQr->qr_token_enc));
});

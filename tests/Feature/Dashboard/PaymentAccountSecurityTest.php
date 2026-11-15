<?php

use App\Mail\PaymentAccountChangedMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePaymentAccount;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('requires re-authentication, enforces 24h visibility delay on 2nd account, sends T10 emails, and rejects staff', function (): void {
    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $owner->update(['password' => Hash::make('OwnerSecret123!')]);

    $staff = User::query()->where('email', 'staff1@example.test')->firstOrFail();
    $venue = Venue::query()->where('owner_id', $owner->id)->firstOrFail();

    // Staff cannot add payment account
    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/payment-accounts', [
            'kind' => VenuePaymentAccount::KIND_BANK,
            'bank_name' => 'BCA',
            'account_number' => '111222333',
            'account_holder' => 'Staff',
            'current_password' => 'PasswordDev123!',
        ])
        ->assertForbidden();

    // Owner without valid current_password is rejected
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/payment-accounts', [
            'kind' => VenuePaymentAccount::KIND_BANK,
            'bank_name' => 'Mandiri',
            'account_number' => '9876543210',
            'account_holder' => 'Owner Venue',
            'current_password' => 'WrongPassword!',
        ])
        ->assertSessionHasErrors('current_password');

    // Clear existing accounts to test 1st account visible immediately vs 2nd delayed 24h
    VenuePaymentAccount::withoutGlobalScopes()->where('venue_id', $venue->id)->delete();

    // 1st account: visible_from = now()
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/payment-accounts', [
            'kind' => VenuePaymentAccount::KIND_BANK,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder' => 'Owner Venue',
            'current_password' => 'OwnerSecret123!',
        ])
        ->assertRedirect();

    $first = VenuePaymentAccount::withoutGlobalScopes()->where('venue_id', $venue->id)->firstOrFail();
    expect($first->visible_from->lte(now()->addMinute()))->toBeTrue();

    // 2nd account: visible_from = now() + 24 hours
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/payment-accounts', [
            'kind' => VenuePaymentAccount::KIND_BANK,
            'bank_name' => 'BNI',
            'account_number' => '5556667778',
            'account_holder' => 'Owner Venue',
            'current_password' => 'OwnerSecret123!',
        ])
        ->assertRedirect();

    $second = VenuePaymentAccount::withoutGlobalScopes()
        ->where('venue_id', $venue->id)
        ->where('id', '!=', $first->id)
        ->firstOrFail();

    expect($second->visible_from->greaterThanOrEqualTo(now()->addHours(23)))->toBeTrue();

    Mail::assertSent(PaymentAccountChangedMail::class);
    expect(AuditLog::query()->where('venue_id', $venue->id)->where('action', 'payment_account.created')->count())->toBe(2);
});

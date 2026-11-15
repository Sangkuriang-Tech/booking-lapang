<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Booking\CustomerBookingController;
use App\Http\Controllers\Booking\PaymentProofController;
use App\Http\Controllers\Booking\VenueQrisController;
use App\Http\Controllers\Dashboard\CheckInController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\OtsBookingController;
use App\Http\Controllers\Dashboard\PaymentValidationController;
use App\Http\Controllers\Dashboard\ScheduleBlockController;
use App\Http\Controllers\Dashboard\VenueSelectionController;
use App\Http\Controllers\Public\LandingController;
use App\Http\Controllers\Public\PublicVenueController;
use App\Http\Controllers\Public\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/venues', [PublicVenueController::class, 'index'])->name('venues.index');
Route::get('/venue/{slug}', [PublicVenueController::class, 'show'])->name('venues.show');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/llms.txt', [SeoController::class, 'llms'])->name('seo.llms');

Route::middleware('guest')->group(function (): void {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    Route::get('/staff/login', [StaffAuthController::class, 'show'])->name('staff.login');
    Route::post('/staff/login', [StaffAuthController::class, 'store'])->name('staff.login.store');
});

Route::post('/logout', [StaffAuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:customer'])
    ->prefix('bookings')
    ->name('bookings.')
    ->group(function (): void {
        Route::get('/', [CustomerBookingController::class, 'index'])->name('index');
        Route::post('/', [CustomerBookingController::class, 'store'])
            ->middleware('throttle:booking-hold')
            ->name('store');
        Route::get('/{ulid}', [CustomerBookingController::class, 'show'])->name('show');
        Route::post('/{ulid}/proof', [CustomerBookingController::class, 'submitProof'])
            ->middleware('throttle:payment-proof')
            ->name('proof.store');
        Route::post('/{ulid}/cancel', [CustomerBookingController::class, 'cancel'])->name('cancel');
    });

Route::middleware('auth')->group(function (): void {
    Route::get('/payments/{ulid}/proof', [PaymentProofController::class, 'show'])
        ->name('payments.proof.show');
    Route::get('/venues/{venueUlid}/payment-accounts/{accountId}/qris', [VenueQrisController::class, 'show'])
        ->whereNumber('accountId')
        ->name('venues.payment-accounts.qris');
});

Route::middleware(['auth', 'role:staff,superadmin'])
    ->prefix('dashboard')
    ->name('dashboard.')
    ->group(function (): void {
        Route::get('/venues/select', [VenueSelectionController::class, 'index'])->name('venues.select');
        Route::post('/venues/select', [VenueSelectionController::class, 'store'])->name('venues.select.store');

        Route::middleware('venue.selected')->group(function (): void {
            Route::get('/', [DashboardController::class, 'index'])->name('index');

            Route::get('/validations', [PaymentValidationController::class, 'index'])->name('validations.index');
            Route::post('/validations/{ulid}', [PaymentValidationController::class, 'update'])->name('validations.update');

            Route::get('/check-in', [CheckInController::class, 'index'])->name('check-in.index');
            Route::post('/check-in/lookup', [CheckInController::class, 'lookup'])->name('check-in.lookup');
            Route::post('/check-in', [CheckInController::class, 'store'])->name('check-in.store');
            Route::post('/check-in/{ulid}/complete', [CheckInController::class, 'complete'])->name('check-in.complete');

            Route::get('/ots', [OtsBookingController::class, 'create'])->name('ots.create');
            Route::post('/ots', [OtsBookingController::class, 'store'])->name('ots.store');

            Route::get('/schedule', [ScheduleBlockController::class, 'index'])->name('schedule.index');
            Route::post('/schedule/blocks', [ScheduleBlockController::class, 'store'])->name('schedule.blocks.store');
            Route::delete('/schedule/blocks/{id}', [ScheduleBlockController::class, 'destroy'])
                ->whereNumber('id')
                ->name('schedule.blocks.destroy');
        });
    });

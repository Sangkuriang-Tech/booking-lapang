<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminVenueController;
use App\Http\Controllers\Admin\CrossVenueTransactionController;
use App\Http\Controllers\Admin\OwnerSubscriptionController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Booking\CustomerBookingController;
use App\Http\Controllers\Booking\PaymentProofController;
use App\Http\Controllers\Booking\VenueQrisController;
use App\Http\Controllers\Dashboard\CheckInController;
use App\Http\Controllers\Dashboard\CourtManagementController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\OtsBookingController;
use App\Http\Controllers\Dashboard\PaymentAccountController;
use App\Http\Controllers\Dashboard\PaymentValidationController;
use App\Http\Controllers\Dashboard\ReportController;
use App\Http\Controllers\Dashboard\ScheduleBlockController;
use App\Http\Controllers\Dashboard\StaffManagementController;
use App\Http\Controllers\Dashboard\VenuePhotoController;
use App\Http\Controllers\Dashboard\VenueSelectionController;
use App\Http\Controllers\Dashboard\VenueSettingsController;
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

            Route::get('/settings/venue', [VenueSettingsController::class, 'edit'])->name('settings.venue.edit');
            Route::put('/settings/venue', [VenueSettingsController::class, 'update'])->name('settings.venue.update');
            Route::put('/settings/operating-hours', [VenueSettingsController::class, 'updateHours'])->name('settings.hours.update');

            Route::post('/photos', [VenuePhotoController::class, 'store'])->name('photos.store');
            Route::post('/photos/{id}/cover', [VenuePhotoController::class, 'setCover'])->whereNumber('id')->name('photos.cover');
            Route::delete('/photos/{id}', [VenuePhotoController::class, 'destroy'])->whereNumber('id')->name('photos.destroy');

            Route::get('/courts', [CourtManagementController::class, 'index'])->name('courts.index');
            Route::post('/court-categories', [CourtManagementController::class, 'storeCategory'])->name('court-categories.store');
            Route::put('/court-categories/{id}', [CourtManagementController::class, 'updateCategory'])->whereNumber('id')->name('court-categories.update');
            Route::delete('/court-categories/{id}', [CourtManagementController::class, 'destroyCategory'])->whereNumber('id')->name('court-categories.destroy');

            Route::post('/courts', [CourtManagementController::class, 'storeCourt'])->name('courts.store');
            Route::put('/courts/{ulid}', [CourtManagementController::class, 'updateCourt'])->name('courts.update');
            Route::delete('/courts/{ulid}', [CourtManagementController::class, 'destroyCourt'])->name('courts.destroy');

            Route::post('/courts/{ulid}/price-rules', [CourtManagementController::class, 'storePriceRule'])->name('price-rules.store');
            Route::put('/price-rules/{ruleId}', [CourtManagementController::class, 'updatePriceRule'])->whereNumber('ruleId')->name('price-rules.update');
            Route::delete('/price-rules/{ruleId}', [CourtManagementController::class, 'destroyPriceRule'])->whereNumber('ruleId')->name('price-rules.destroy');

            Route::get('/payment-accounts', [PaymentAccountController::class, 'index'])->name('payment-accounts.index');
            Route::post('/payment-accounts', [PaymentAccountController::class, 'store'])->name('payment-accounts.store');
            Route::put('/payment-accounts/{id}', [PaymentAccountController::class, 'update'])->whereNumber('id')->name('payment-accounts.update');
            Route::post('/payment-accounts/{id}/toggle', [PaymentAccountController::class, 'toggle'])->whereNumber('id')->name('payment-accounts.toggle');

            Route::get('/staff', [StaffManagementController::class, 'index'])->name('staff.index');
            Route::post('/staff', [StaffManagementController::class, 'store'])->name('staff.store');
            Route::delete('/staff/{userUlid}', [StaffManagementController::class, 'destroy'])->name('staff.destroy');

            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
        });
    });

Route::middleware(['auth', 'role:superadmin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/plans', [SubscriptionPlanController::class, 'index'])->name('plans.index');
        Route::post('/plans', [SubscriptionPlanController::class, 'store'])->name('plans.store');
        Route::put('/plans/{id}', [SubscriptionPlanController::class, 'update'])->whereNumber('id')->name('plans.update');

        Route::get('/subscriptions', [OwnerSubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('/subscriptions', [OwnerSubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::post('/subscriptions/{id}/status', [OwnerSubscriptionController::class, 'updateStatus'])->whereNumber('id')->name('subscriptions.status');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::post('/users/{ulid}/status', [AdminUserController::class, 'updateStatus'])->name('users.status');

        Route::get('/venues', [AdminVenueController::class, 'index'])->name('venues.index');
        Route::post('/venues', [AdminVenueController::class, 'store'])->name('venues.store');

        Route::get('/transactions', [CrossVenueTransactionController::class, 'index'])->name('transactions.index');
    });

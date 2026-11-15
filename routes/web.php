<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\StaffAuthController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\VenueSelectionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Home');
})->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    Route::get('/staff/login', [StaffAuthController::class, 'show'])->name('staff.login');
    Route::post('/staff/login', [StaffAuthController::class, 'store'])->name('staff.login.store');
});

Route::post('/logout', [StaffAuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:staff,superadmin'])
    ->prefix('dashboard')
    ->name('dashboard.')
    ->group(function (): void {
        Route::get('/venues/select', [VenueSelectionController::class, 'index'])->name('venues.select');
        Route::post('/venues/select', [VenueSelectionController::class, 'store'])->name('venues.select.store');

        Route::middleware('venue.selected')->group(function (): void {
            Route::get('/', [DashboardController::class, 'index'])->name('index');
        });
    });

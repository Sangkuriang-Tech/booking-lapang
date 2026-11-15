<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateStaff;
use App\Actions\Auth\LogoutUser;
use App\Actions\Auth\SelectActiveVenue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StaffLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffAuthController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/StaffLogin');
    }

    public function store(
        StaffLoginRequest $request,
        AuthenticateStaff $authenticateStaff,
        SelectActiveVenue $selectActiveVenue,
    ): RedirectResponse {
        $throttleKey = Str::transliterate(Str::lower($request->string('email')).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => ["Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik."],
            ]);
        }

        try {
            $user = $authenticateStaff->execute(
                email: $request->string('email')->toString(),
                password: $request->string('password')->toString(),
                remember: $request->boolean('remember'),
            );
        } catch (ValidationException $exception) {
            RateLimiter::hit($throttleKey, 60);

            throw $exception;
        }

        RateLimiter::clear($throttleKey);

        if ($user->isSuperadmin()) {
            return redirect()->intended(route('dashboard.index'));
        }

        $activeVenue = $selectActiveVenue->resolveAfterLogin($user);

        if ($activeVenue === null) {
            return redirect()->route('dashboard.venues.select');
        }

        return redirect()->intended(route('dashboard.index'));
    }

    public function destroy(Request $request, LogoutUser $logoutUser): RedirectResponse
    {
        $logoutUser->execute();

        return redirect()->route('home');
    }
}

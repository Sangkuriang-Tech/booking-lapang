<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\HandleGoogleCallback;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(HandleGoogleCallback $action): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();
        $action->execute($googleUser);

        return redirect()->intended(route('home'));
    }
}

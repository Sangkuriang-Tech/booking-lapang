<?php

namespace App\Actions\Auth;

use App\Support\VenueContext;
use Illuminate\Support\Facades\Auth;

class LogoutUser
{
    public function __construct(
        private readonly VenueContext $venueContext,
    ) {}

    public function execute(): void
    {
        $this->venueContext->clear();

        Auth::logout();

        if (app()->bound('request') && request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }
}

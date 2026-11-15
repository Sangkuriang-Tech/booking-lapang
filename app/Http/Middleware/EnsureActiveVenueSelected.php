<?php

namespace App\Http\Middleware;

use App\Support\VenueContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveVenueSelected
{
    public function __construct(
        private readonly VenueContext $venueContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('staff.login');
        }

        if ($user->isSuperadmin()) {
            return $next($request);
        }

        if ($user->isStaff() && $this->venueContext->id() === null) {
            return redirect()->route('dashboard.venues.select');
        }

        return $next($request);
    }
}

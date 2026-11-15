<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\GetCrossVenueTransactions;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CrossVenueTransactionController extends Controller
{
    public function index(Request $request, GetCrossVenueTransactions $action): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $filters = [
            'venue_ulid' => $request->string('venue_ulid')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'play_date' => $request->string('play_date')->toString() ?: null,
        ];

        $paginator = $action->execute($actor, $filters);

        $transactions = $paginator->through(fn (Booking $booking): array => [
            'ulid' => $booking->ulid,
            'code' => $booking->code,
            'venue_name' => $booking->venue?->name ?? '-',
            'court_name' => $booking->court?->name ?? '-',
            'customer_name' => $booking->customer_name,
            'play_date' => $booking->play_date->toDateString(),
            'status' => $booking->status,
            'source' => $booking->source,
            'total_amount' => $booking->price_total,
            'paid_amount' => $booking->paid_total,
        ]);

        return Inertia::render('Admin/Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $filters,
            'venues' => Venue::query()->orderBy('name')->get(['ulid', 'name']),
        ]);
    }
}

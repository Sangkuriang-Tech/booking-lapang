<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePaymentAccount;
use finfo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class VenueQrisController extends Controller
{
    public function show(Request $request, string $venueUlid, int $accountId): Response
    {
        /** @var User $user */
        $user = $request->user();

        $venue = Venue::query()
            ->where('ulid', $venueUlid)
            ->firstOrFail();

        $account = VenuePaymentAccount::query()
            ->withoutGlobalScopes()
            ->where('id', $accountId)
            ->where('venue_id', $venue->id)
            ->where('kind', VenuePaymentAccount::KIND_QRIS)
            ->where('is_active', true)
            ->firstOrFail();

        if ($account->visible_from->isFuture()) {
            abort(403, 'Rekening QRIS belum tersedia untuk ditampilkan.');
        }

        if (! $this->userCanAccessQris($user, $venue)) {
            abort(403);
        }

        if ($account->qris_image_path === null || ! Storage::disk('local')->exists($account->qris_image_path)) {
            abort(404);
        }

        $bytes = Storage::disk('local')->get($account->qris_image_path);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($bytes) ?: 'image/png';

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="qris-'.$venue->slug.'.png"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function userCanAccessQris(User $user, Venue $venue): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        if ($user->isCustomer()) {
            return Booking::query()
                ->withoutGlobalScopes()
                ->where('venue_id', $venue->id)
                ->where('customer_id', $user->id)
                ->whereIn('status', Booking::LOCKED_STATUSES)
                ->exists();
        }

        return $user->venues()
            ->where('venues.id', $venue->id)
            ->exists();
    }
}

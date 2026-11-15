<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class PaymentProofController extends Controller
{
    public function show(string $ulid): Response
    {
        $payment = Payment::query()
            ->withoutGlobalScopes()
            ->where('ulid', $ulid)
            ->with([
                'booking' => static fn ($q) => $q->withoutGlobalScopes()->select('id', 'customer_id', 'venue_id'),
            ])
            ->firstOrFail();

        Gate::authorize('view', $payment);

        if (
            $payment->proof_path === null
            || $payment->proof_deleted_at !== null
            || ! Storage::disk('local')->exists($payment->proof_path)
        ) {
            abort(404);
        }

        $encryptedBytes = Storage::disk('local')->get($payment->proof_path);
        $decryptedBytes = Crypt::decrypt($encryptedBytes);

        return response($decryptedBytes, 200, [
            'Content-Type' => $payment->proof_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="bukti-pembayaran-'.$payment->ulid.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}

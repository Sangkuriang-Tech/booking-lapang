<?php

namespace App\Actions\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class HandleGoogleCallback
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(SocialiteUser $googleUser): User
    {
        $email = strtolower(trim((string) $googleUser->getEmail()));
        $googleId = (string) $googleUser->getId();

        if ($email === '' || $googleId === '') {
            throw ValidationException::withMessages([
                'email' => ['Akun Google tidak memiliki email atau ID yang valid.'],
            ]);
        }

        $isVerified = (bool) data_get($googleUser->user ?? [], 'email_verified', true);
        if (! $isVerified) {
            throw ValidationException::withMessages([
                'email' => ['Email Google Anda belum terverifikasi.'],
            ]);
        }

        $user = User::query()
            ->where('google_id', $googleId)
            ->orWhereRaw('lower(email) = ?', [$email])
            ->first();

        if ($user !== null) {
            if (! $user->isCustomer()) {
                throw ValidationException::withMessages([
                    'email' => ['Akun staf atau superadmin wajib masuk melalui halaman login staf.'],
                ]);
            }

            if (! $user->is_active) {
                throw ValidationException::withMessages([
                    'email' => ['Akun Anda sedang dinonaktifkan.'],
                ]);
            }

            $user->forceFill([
                'google_id' => $googleId,
                'name' => $googleUser->getName() ?: $user->name,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'last_login_at' => now(),
            ])->save();
        } else {
            $user = User::query()->create([
                'role' => User::ROLE_CUSTOMER,
                'name' => $googleUser->getName() ?: 'Pembooking',
                'email' => $email,
                'google_id' => $googleId,
                'email_verified_at' => now(),
                'is_active' => true,
                'last_login_at' => now(),
            ]);
        }

        Auth::login($user, remember: true);

        if (app()->bound('request') && request()->hasSession()) {
            request()->session()->regenerate();
        }

        $this->recordAuditLog->execute(
            action: 'auth.google_login',
            actor: $user,
            subject: $user,
        );

        return $user;
    }
}

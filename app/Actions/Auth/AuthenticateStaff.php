<?php

namespace App\Actions\Auth;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateStaff
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(string $email, string $password, bool $remember = false): User
    {
        $normalizedEmail = strtolower(trim($email));

        $user = User::query()
            ->whereRaw('lower(email) = ?', [$normalizedEmail])
            ->first();

        if (
            $user === null
            || $user->isCustomer()
            || ! $user->is_active
            || $user->password === null
            || ! Hash::check($password, $user->password)
        ) {
            $this->recordAuditLog->execute(
                action: 'auth.staff_login_failed',
                actor: $user,
                newValues: ['email_masked' => $this->maskEmail($normalizedEmail)],
            );

            throw ValidationException::withMessages([
                'email' => ['Email atau kata sandi tidak valid.'],
            ]);
        }

        Auth::login($user, $remember);

        if (app()->bound('request') && request()->hasSession()) {
            request()->session()->regenerate();
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $this->recordAuditLog->execute(
            action: 'auth.staff_login',
            actor: $user,
            subject: $user,
        );

        return $user;
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        if ($local === '' || $domain === '') {
            return '***';
        }

        return substr($local, 0, 1).'***@'.$domain;
    }
}

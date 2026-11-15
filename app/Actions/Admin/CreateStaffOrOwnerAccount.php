<?php

namespace App\Actions\Admin;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateStaffOrOwnerAccount
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data): User {
            $user = User::query()->create([
                'role' => User::ROLE_STAFF,
                'name' => trim((string) $data['name']),
                'email' => mb_strtolower(trim((string) $data['email'])),
                'phone' => $data['phone'] ?? null,
                'email_verified_at' => now(),
                'password' => Hash::make((string) $data['password']),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $this->recordAuditLog->execute(
                action: 'user.staff_created',
                actor: $actor,
                subject: $user,
                newValues: ['name' => $user->name, 'role' => $user->role],
            );

            return $user;
        });
    }
}

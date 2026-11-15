<?php

namespace App\Actions\Admin;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateUserStatus
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, User $targetUser, bool $isActive): User
    {
        if ($actor->id === $targetUser->id && ! $isActive) {
            throw ValidationException::withMessages([
                'is_active' => ['Anda tidak dapat menonaktifkan akun Anda sendiri.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $targetUser, $isActive): User {
            $oldStatus = $targetUser->is_active;

            $targetUser->update(['is_active' => $isActive]);
            $fresh = $targetUser->fresh();

            $this->recordAuditLog->execute(
                action: 'user.status_updated',
                actor: $actor,
                subject: $fresh,
                oldValues: ['is_active' => $oldStatus],
                newValues: ['is_active' => $fresh->is_active],
            );

            return $fresh;
        });
    }
}

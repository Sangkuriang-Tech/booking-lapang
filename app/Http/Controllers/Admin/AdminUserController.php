<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateStaffOrOwnerAccount;
use App\Actions\Admin\UpdateUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserStatusRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('venues:id,ulid,name')
            ->where('role', User::ROLE_STAFF)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'ulid' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'venues' => $user->venues->map(fn ($v): array => [
                    'name' => $v->name,
                    'role' => $v->pivot->role,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function store(StoreAdminUserRequest $request, CreateStaffOrOwnerAccount $action): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $request->validated());

        return back()->with('success', 'Akun pemilik/staf baru berhasil dibuat.');
    }

    public function updateStatus(
        UpdateAdminUserStatusRequest $request,
        string $ulid,
        UpdateUserStatus $action,
    ): RedirectResponse {
        $targetUser = User::query()->where('ulid', $ulid)->where('role', User::ROLE_STAFF)->firstOrFail();

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $targetUser, (bool) $request->validated('is_active'));

        return back()->with('success', 'Status akun berhasil diperbarui.');
    }
}

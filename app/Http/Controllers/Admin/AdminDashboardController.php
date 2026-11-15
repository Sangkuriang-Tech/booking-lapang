<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $latestSubscriptions = Subscription::query()
            ->with(['owner:id,ulid,name,email', 'plan:id,name,code'])
            ->orderByDesc('ends_on')
            ->orderByDesc('id')
            ->get()
            ->unique('owner_id')
            ->map(function (Subscription $sub): array {
                $status = $sub->statusOn();

                return [
                    'id' => $sub->id,
                    'owner_name' => $sub->owner?->name,
                    'owner_email' => $sub->owner?->email,
                    'plan_name' => $sub->plan?->name,
                    'starts_on' => $sub->starts_on->toDateString(),
                    'ends_on' => $sub->ends_on->toDateString(),
                    'status' => $status,
                    'is_active' => $sub->is_active,
                ];
            })
            ->values();

        return Inertia::render('Admin/Dashboard/Index', [
            'counts' => [
                'active' => $latestSubscriptions->where('status', Subscription::STATUS_ACTIVE)->count(),
                'grace_period' => $latestSubscriptions->where('status', Subscription::STATUS_GRACE_PERIOD)->count(),
                'inactive' => $latestSubscriptions->where('status', Subscription::STATUS_INACTIVE)->count(),
                'venues' => Venue::query()->count(),
                'staff_accounts' => User::query()->where('role', User::ROLE_STAFF)->count(),
            ],
            'subscriptionsByStatus' => [
                'active' => $latestSubscriptions->where('status', Subscription::STATUS_ACTIVE)->values()->all(),
                'grace_period' => $latestSubscriptions->where('status', Subscription::STATUS_GRACE_PERIOD)->values()->all(),
                'inactive' => $latestSubscriptions->where('status', Subscription::STATUS_INACTIVE)->values()->all(),
            ],
        ]);
    }
}

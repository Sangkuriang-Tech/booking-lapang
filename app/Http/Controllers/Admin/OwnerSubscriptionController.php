<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\AssignOwnerSubscription;
use App\Actions\Admin\ToggleSubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionRequest;
use App\Http\Requests\Admin\UpdateSubscriptionStatusRequest;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OwnerSubscriptionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Subscription::class);

        $subscriptions = Subscription::query()
            ->with(['owner:id,ulid,name,email', 'plan:id,code,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Subscription $sub): array => [
                'id' => $sub->id,
                'owner_ulid' => $sub->owner?->ulid,
                'owner_name' => $sub->owner?->name,
                'owner_email' => $sub->owner?->email,
                'plan_name' => $sub->plan?->name,
                'starts_on' => $sub->starts_on->toDateString(),
                'ends_on' => $sub->ends_on->toDateString(),
                'is_active' => $sub->is_active,
                'status' => $sub->statusOn(),
                'max_venues' => $sub->max_venues,
                'max_courts_per_venue' => $sub->max_courts_per_venue,
                'max_staff_total' => $sub->max_staff_total,
                'max_payment_accounts_per_venue' => $sub->max_payment_accounts_per_venue,
                'can_export_reports' => $sub->can_export_reports,
                'note' => $sub->note,
            ])
            ->values()
            ->all();

        $owners = User::query()
            ->where('role', User::ROLE_STAFF)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['ulid', 'name', 'email']);

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('price_monthly')
            ->get(['id', 'code', 'name', 'price_monthly']);

        return Inertia::render('Admin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'owners' => $owners,
            'plans' => $plans,
        ]);
    }

    public function store(StoreSubscriptionRequest $request, AssignOwnerSubscription $action): RedirectResponse
    {
        $this->authorize('create', Subscription::class);

        $owner = User::query()->where('ulid', $request->validated('owner_ulid'))->firstOrFail();
        $plan = SubscriptionPlan::query()->findOrFail($request->validated('plan_id'));

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $owner, $plan, $request->validated());

        return back()->with('success', 'Langganan pemilik berhasil ditetapkan.');
    }

    public function updateStatus(
        UpdateSubscriptionStatusRequest $request,
        int $id,
        ToggleSubscriptionStatus $action,
    ): RedirectResponse {
        $subscription = Subscription::query()->findOrFail($id);
        $this->authorize('update', $subscription);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute(
            $actor,
            $subscription,
            (bool) $request->validated('is_active'),
            $request->validated('ends_on'),
        );

        return back()->with('success', 'Status langganan berhasil diperbarui.');
    }
}

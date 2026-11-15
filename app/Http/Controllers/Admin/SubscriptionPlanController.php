<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CreateSubscriptionPlan;
use App\Actions\Admin\UpdateSubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSubscriptionPlanRequest;
use App\Http\Requests\Admin\UpdateSubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionPlanController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SubscriptionPlan::class);

        $plans = SubscriptionPlan::query()
            ->orderBy('price_monthly')
            ->get()
            ->map(fn (SubscriptionPlan $plan): array => [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'price_monthly' => $plan->price_monthly,
                'max_venues' => $plan->max_venues,
                'max_courts_per_venue' => $plan->max_courts_per_venue,
                'max_staff_total' => $plan->max_staff_total,
                'max_payment_accounts_per_venue' => $plan->max_payment_accounts_per_venue,
                'proof_retention_days' => $plan->proof_retention_days,
                'can_export_reports' => $plan->can_export_reports,
                'is_active' => $plan->is_active,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function store(StoreSubscriptionPlanRequest $request, CreateSubscriptionPlan $action): RedirectResponse
    {
        $this->authorize('create', SubscriptionPlan::class);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $request->validated());

        return back()->with('success', 'Paket langganan berhasil ditambahkan.');
    }

    public function update(UpdateSubscriptionPlanRequest $request, int $id, UpdateSubscriptionPlan $action): RedirectResponse
    {
        $plan = SubscriptionPlan::query()->findOrFail($id);
        $this->authorize('update', $plan);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $plan, $request->validated());

        return back()->with('success', 'Paket langganan berhasil diperbarui.');
    }
}

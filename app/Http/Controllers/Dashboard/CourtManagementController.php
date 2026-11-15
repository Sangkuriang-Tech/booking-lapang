<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Court\CreateCourt;
use App\Actions\Court\CreateCourtCategory;
use App\Actions\Court\DeleteCourt;
use App\Actions\Court\DeleteCourtCategory;
use App\Actions\Court\UpdateCourt;
use App\Actions\Court\UpdateCourtCategory;
use App\Actions\Pricing\CreatePriceRule;
use App\Actions\Pricing\DeletePriceRule;
use App\Actions\Pricing\UpdatePriceRule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreCourtCategoryRequest;
use App\Http\Requests\Dashboard\StoreCourtRequest;
use App\Http\Requests\Dashboard\StorePriceRuleRequest;
use App\Http\Requests\Dashboard\UpdateCourtCategoryRequest;
use App\Http\Requests\Dashboard\UpdateCourtRequest;
use App\Http\Requests\Dashboard\UpdatePriceRuleRequest;
use App\Models\Court;
use App\Models\CourtCategory;
use App\Models\PriceRule;
use App\Models\Sport;
use App\Models\User;
use App\Support\VenueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourtManagementController extends Controller
{
    public function index(Request $request, VenueContext $venueContext): Response
    {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('viewAny', Court::class);

        $categories = CourtCategory::query()
            ->where('venue_id', $venue->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $courts = Court::query()
            ->with([
                'sport:id,name,slug',
                'category:id,name',
                'priceRules' => fn ($q) => $q->orderBy('day_type')->orderBy('start_min'),
            ])
            ->where('venue_id', $venue->id)
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Court $court): array => [
                'id' => $court->id,
                'ulid' => $court->ulid,
                'name' => $court->name,
                'description' => $court->description,
                'sport_id' => $court->sport_id,
                'sport_name' => $court->sport?->name,
                'category_id' => $court->category_id,
                'category_name' => $court->category?->name,
                'is_active' => $court->is_active,
                'sort_order' => $court->sort_order,
                'price_rules' => $court->priceRules->map(fn (PriceRule $rule): array => [
                    'id' => $rule->id,
                    'day_type' => $rule->day_type,
                    'start_min' => $rule->start_min,
                    'end_min' => $rule->end_min,
                    'price_per_hour' => $rule->price_per_hour,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $subscription = $venue->activeSubscription();

        return Inertia::render('Dashboard/Courts/Index', [
            'categories' => $categories,
            'courts' => $courts,
            'sports' => Sport::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'quota' => [
                'used' => count($courts),
                'max' => $subscription?->max_courts_per_venue,
            ],
        ]);
    }

    public function storeCategory(
        StoreCourtCategoryRequest $request,
        VenueContext $venueContext,
        CreateCourtCategory $action,
    ): RedirectResponse {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('create', CourtCategory::class);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $venue, $request->validated('name'));

        return back()->with('success', 'Kategori lapangan berhasil ditambahkan.');
    }

    public function updateCategory(
        UpdateCourtCategoryRequest $request,
        int $id,
        UpdateCourtCategory $action,
    ): RedirectResponse {
        $category = CourtCategory::query()->findOrFail($id);
        $this->authorize('update', $category);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $category, $request->validated('name'));

        return back()->with('success', 'Kategori lapangan berhasil diperbarui.');
    }

    public function destroyCategory(int $id, Request $request, DeleteCourtCategory $action): RedirectResponse
    {
        $category = CourtCategory::query()->findOrFail($id);
        $this->authorize('delete', $category);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $category);

        return back()->with('success', 'Kategori lapangan berhasil dihapus.');
    }

    public function storeCourt(
        StoreCourtRequest $request,
        VenueContext $venueContext,
        CreateCourt $action,
    ): RedirectResponse {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('create', Court::class);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $venue, $request->validated());

        return back()->with('success', 'Lapangan berhasil ditambahkan.');
    }

    public function updateCourt(
        UpdateCourtRequest $request,
        string $ulid,
        UpdateCourt $action,
    ): RedirectResponse {
        $court = Court::query()->where('ulid', $ulid)->firstOrFail();
        $this->authorize('update', $court);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $court, $request->validated());

        return back()->with('success', 'Data lapangan berhasil diperbarui.');
    }

    public function destroyCourt(string $ulid, Request $request, DeleteCourt $action): RedirectResponse
    {
        $court = Court::query()->where('ulid', $ulid)->firstOrFail();
        $this->authorize('delete', $court);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $court);

        return back()->with('success', 'Lapangan berhasil dihapus.');
    }

    public function storePriceRule(
        StorePriceRuleRequest $request,
        string $ulid,
        CreatePriceRule $action,
    ): RedirectResponse {
        $court = Court::query()->where('ulid', $ulid)->firstOrFail();
        $this->authorize('update', $court);
        $this->authorize('create', PriceRule::class);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $court, $request->validated());

        return back()->with('success', 'Aturan harga berhasil ditambahkan.');
    }

    public function updatePriceRule(
        UpdatePriceRuleRequest $request,
        int $ruleId,
        UpdatePriceRule $action,
    ): RedirectResponse {
        $rule = PriceRule::query()->findOrFail($ruleId);
        $this->authorize('update', $rule);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $rule, $request->validated());

        return back()->with('success', 'Aturan harga berhasil diperbarui.');
    }

    public function destroyPriceRule(int $ruleId, Request $request, DeletePriceRule $action): RedirectResponse
    {
        $rule = PriceRule::query()->findOrFail($ruleId);
        $this->authorize('delete', $rule);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $rule);

        return back()->with('success', 'Aturan harga berhasil dihapus.');
    }
}

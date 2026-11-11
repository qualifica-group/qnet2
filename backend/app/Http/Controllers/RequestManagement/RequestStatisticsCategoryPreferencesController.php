<?php

declare(strict_types=1);

namespace App\Http\Controllers\RequestManagement;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RequestManagement\UpdateRequestStatisticsCategoryPreferencesRequest;
use App\Http\Resources\CategoryTabPreferencesResource;
use App\Models\UserCategoryTabPreference;
use App\RequestManagement\RequestModule;
use App\Services\CategoryTabs\CategoryTabPreferenceStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * GET/PUT /api/request-management/report/category-tab-preferences (spec
 * 0385): the actor's favourite categories on the statistics tab strip.
 * Gated by `request-statistics.view` alone, so a statistics-only user, who
 * cannot reach Gestione Richieste's own endpoint (spec 0184), keeps
 * favourites too. Same storage and payload as that endpoint, on a row of
 * its own, always keyed on the authenticated user, never on input.
 */
class RequestStatisticsCategoryPreferencesController extends BaseApiController
{
    /** The `user_category_tab_preferences.module` value of the statistics strip. */
    public const string PREFERENCE_MODULE = 'request-statistics';

    public function __construct(private readonly CategoryTabPreferenceStore $store) {}

    public function show(Request $request): JsonResponse
    {
        try {
            $this->authorizeStatistics($request);

            // No competence default here (D-2): the statistics strip starts
            // with every category, exactly as before favourites existed.
            return $this->respond($this->store->read($request->user(), self::PREFERENCE_MODULE, static fn (): array => []));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateRequestStatisticsCategoryPreferencesRequest $request): JsonResponse
    {
        try {
            $this->authorizeStatistics($request);

            return $this->respond($this->store->save(
                $request->user(),
                self::PREFERENCE_MODULE,
                $request->safe()->only(['favorite_category_ids', 'show_only_favorites']),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function authorizeStatistics(Request $request): void
    {
        Gate::forUser($request->user())->authorize(RequestModule::STATISTICS_PERMISSION);
    }

    private function respond(UserCategoryTabPreference $preference): JsonResponse
    {
        return $this->ok(['preferences' => new CategoryTabPreferencesResource($preference)]);
    }
}

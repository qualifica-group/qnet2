<?php

declare(strict_types=1);

namespace App\Http\Controllers\CategoryTabs;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RequestManagement\UpdateCategoryTabPreferencesRequest;
use App\Http\Resources\CategoryTabPreferencesResource;
use App\Models\UserCategoryTabPreference;
use App\Services\CategoryTabs\CategoryTabPreferenceStore;
use App\Services\CategoryTabs\GridCategoryTabsResolver;
use App\Tables\CategoryTabs\CategoryTabGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * GET/PUT /api/{grid}/category-tab-preferences for `opportunities` and
 * `quotes` (spec 0335): same contract as Gestione Richieste's, keyed on the
 * authenticated user and on the grid of the matched route, never on input.
 */
class GridCategoryTabPreferencesController extends BaseApiController
{
    public function __construct(
        private readonly CategoryTabPreferenceStore $store,
        private readonly GridCategoryTabsResolver $tabs,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            $grid = $this->authorizedGrid($request);
            $user = $request->user();

            return $this->respond($this->store->read(
                $user,
                $grid->value,
                fn (): array => $this->tabs->resolve($user, $grid)->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateCategoryTabPreferencesRequest $request): JsonResponse
    {
        try {
            $grid = $this->authorizedGrid($request);

            return $this->respond($this->store->save(
                $request->user(),
                $grid->value,
                $request->safe()->only(['favorite_category_ids', 'show_only_favorites']),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function authorizedGrid(Request $request): CategoryTabGrid
    {
        $grid = CategoryTabGrid::fromRequest($request);
        Gate::forUser($request->user())->authorize($grid->permission('viewAny'));

        return $grid;
    }

    private function respond(UserCategoryTabPreference $preference): JsonResponse
    {
        return $this->ok(['preferences' => new CategoryTabPreferencesResource($preference)]);
    }
}

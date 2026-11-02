<?php

declare(strict_types=1);

namespace App\Http\Controllers\RequestManagement;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CategoryTabs\CategoryTabLevelRequest;
use App\Http\Requests\RequestManagement\UpdateCategoryTabPreferencesRequest;
use App\Http\Resources\CategoryTabPreferencesResource;
use App\Models\UserCategoryTabPreference;
use App\RequestManagement\RequestModule;
use App\Services\CategoryTabs\CategoryTabPreferenceStore;
use App\Services\RequestManagement\RequestCategoryTabsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * GET/PUT /api/{module}/category-tab-preferences (spec 0184): the actor's
 * favourite categories on the module's tab strip. Always keyed on the
 * authenticated user and on the module of the matched route, never on input.
 */
class CategoryTabPreferencesController extends BaseApiController
{
    public function __construct(
        private readonly CategoryTabPreferenceStore $store,
        private readonly RequestCategoryTabsResolver $tabs,
    ) {}

    public function show(CategoryTabLevelRequest $request): JsonResponse
    {
        try {
            $module = $this->authorizedModule($request);
            $user = $request->user();

            return $this->respond($this->store->read(
                $user,
                $module->value,
                fn (): array => $this->tabs->resolve($user, $module)->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
                $request->level(),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateCategoryTabPreferencesRequest $request): JsonResponse
    {
        try {
            $module = $this->authorizedModule($request);

            return $this->respond($this->store->save(
                $request->user(),
                $module->value,
                $request->safe()->only(['favorite_category_ids', 'show_only_favorites']),
                $request->level(),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function authorizedModule(Request $request): RequestModule
    {
        $module = RequestModule::fromRequest($request);
        Gate::forUser($request->user())->authorize($module->permission('viewAny'));

        return $module;
    }

    private function respond(UserCategoryTabPreference $preference): JsonResponse
    {
        return $this->ok(['preferences' => new CategoryTabPreferencesResource($preference)]);
    }
}

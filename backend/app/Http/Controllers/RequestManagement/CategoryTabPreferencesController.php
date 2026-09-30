<?php

declare(strict_types=1);

namespace App\Http\Controllers\RequestManagement;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\RequestManagement\UpdateCategoryTabPreferencesRequest;
use App\Http\Resources\CategoryTabPreferencesResource;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\UserCategoryTabPreference;
use App\RequestManagement\RequestModule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET/PUT /api/{module}/category-tab-preferences (spec 0184): the actor's
 * favourite categories on the module's tab strip. Always keyed on the
 * authenticated user and on the module of the matched route, never on input.
 */
class CategoryTabPreferencesController extends BaseApiController
{
    public function show(Request $request): JsonResponse
    {
        try {
            $module = $this->authorizedModule($request);

            return $this->respond($this->preferenceOf($request->user(), $module));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateCategoryTabPreferencesRequest $request): JsonResponse
    {
        try {
            $module = $this->authorizedModule($request);

            $preference = UserCategoryTabPreference::query()->updateOrCreate(
                ['user_id' => $request->user()->id, 'module' => $module->value],
                $request->safe()->only(['favorite_category_ids', 'show_only_favorites']),
            );

            return $this->respond($preference);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function authorizedModule(Request $request): RequestModule
    {
        $module = RequestModule::fromRequest($request);
        abort_unless($request->user()->can($module->permission('viewAny')), 403);

        return $module;
    }

    /** The stored row, or an unsaved one carrying the defaults (no favourites). */
    private function preferenceOf(User $user, RequestModule $module): UserCategoryTabPreference
    {
        return UserCategoryTabPreference::query()->firstOrNew(['user_id' => $user->id, 'module' => $module->value]);
    }

    /** A favourite whose category was deleted since is dropped from the answer (AC-007). */
    private function respond(UserCategoryTabPreference $preference): JsonResponse
    {
        $preference->favorite_category_ids = ProductCategory::query()
            ->whereKey($preference->favorite_category_ids)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        return $this->ok(['preferences' => new CategoryTabPreferencesResource($preference)]);
    }
}

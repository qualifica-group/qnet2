<?php

declare(strict_types=1);

namespace App\Http\Controllers\CategoryTabs;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\CategoryTabs\CategoryTabLevelRequest;
use App\Http\Resources\GridCategoryTabResource;
use App\Services\CategoryTabs\GridCategoryTabsResolver;
use App\Tables\CategoryTabs\CategoryTabGrid;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * GET /api/{grid}/product-categories for `opportunities` and `quotes`
 * (spec 0335): feeds the category tab strip of the two grids.
 */
class GridCategoryTabsController extends BaseApiController
{
    public function __construct(private readonly GridCategoryTabsResolver $resolver) {}

    public function __invoke(CategoryTabLevelRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $grid = CategoryTabGrid::fromRequest($request);
            Gate::forUser($user)->authorize($grid->permission('viewAny'));

            return $this->ok(['categories' => GridCategoryTabResource::collection($this->resolver->resolve($user, $grid, $request->level()))]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}

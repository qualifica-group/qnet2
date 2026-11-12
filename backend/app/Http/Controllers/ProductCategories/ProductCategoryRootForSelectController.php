<?php

namespace App\Http\Controllers\ProductCategories;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ProductCategories\ProductCategoryRootForSelectRequest;
use App\Http\Resources\ProductCategoryBranchForSelectResource;
use App\Services\ProductCategories\ProductCategoryForSelectResolver;
use App\Tables\Shared\ActorParentCategories;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET /api/product-category-roots/for-select — the first-level product
 * categories, feeding the "Categoria madre" grid filter.
 *
 * Same `{id, label}` shape as the branch picker, so it reuses its resource. No permission gate beyond `auth:sanctum` (ADR 0011,
 * amended 2026-07-31). With `actor_roots` an actor limited to some parent
 * categories gets only those, and nobody gets "Tutte": the list pages read an
 * empty filter as every root of the actor (ActorParentCategories).
 *
 * @see ProductCategoryForSelectResolver::resolveRoots
 */
class ProductCategoryRootForSelectController extends BaseApiController
{
    public function __construct(
        private readonly ProductCategoryForSelectResolver $resolver,
        private readonly ActorParentCategories $actorRoots,
    ) {}

    public function __invoke(ProductCategoryRootForSelectRequest $request): JsonResponse
    {
        try {
            $actorRootsOnly = $request->actorRootsOnly();
            $onlyRootIds = $actorRootsOnly ? $this->actorRoots->rootIdsFor($request->user()) : null;
            $result = $this->resolver->resolveRoots($request->toData(), $request->currentOnly(), $request->hideLinkedLegacy(), $onlyRootIds, offerAllRoots: ! $actorRootsOnly);

            return $this->paginatedResponse(
                ProductCategoryBranchForSelectResource::collection($result->items),
                $result->total,
                $result->offset,
                $result->limit,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}

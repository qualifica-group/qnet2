<?php

namespace App\Http\Controllers\ProductCategories;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Exceptions\ProductCategories\BulkMoveConflictException;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ProductCategories\BulkMoveCategoriesRequest;
use App\Http\Requests\ProductCategories\EffectiveAttributesRequest;
use App\Http\Requests\ProductCategories\StoreProductCategoryRequest;
use App\Http\Requests\ProductCategories\UpdateProductCategoryRequest;
use App\Http\Resources\ProductCategoryResource;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ProductCategories\BulkMoveCategories;
use App\Services\ProductCategoryService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD + tree/effective-attributes endpoints for the `product-categories`
 * resource (spec 0017), backing the backend-driven table row-actions plus
 * the dedicated tree view and the product form's dynamic-attributes lookup.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (ProductCategoryPolicy), Service call, response. No business logic, no
 * queries.
 *
 * @see ProductCategoryService
 */
class ProductCategoryController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ProductCategoryService $service,
        private readonly BulkMoveCategories $bulkMove,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/product-categories/tree — the full nested tree (roots first).
     */
    public function tree(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', ProductCategory::class);

            return $this->ok($this->service->tree());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/product-categories/{productCategory}/effective-attributes —
     * own + inherited attributes, for the product form's dynamic fields
     * (`?context=product`) and the request-management preliminary info
     * (`?context=opportunity`, the default — spec 0061 hard invariant).
     *
     * Authorized by product-categories.view OR any of the products
     * view/create/update abilities: a user who may only create/edit products
     * (no product-categories module access) still needs this to render the
     * dynamic form (spec 0017 data_contract note).
     */
    public function effectiveAttributes(EffectiveAttributesRequest $request, ProductCategory $productCategory): JsonResponse
    {
        try {
            $this->authorizeEffectiveAttributes($request->user());

            return $this->ok($this->service->effectiveAttributes($productCategory, $request->context())->values());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['productCategory' => $productCategory->id]);
        }
    }

    /**
     * GET /api/product-categories/{productCategory} — single category (view row-action).
     */
    public function show(Request $request, ProductCategory $productCategory): JsonResponse
    {
        try {
            $this->authorize('view', $productCategory);

            return $this->okWithPermissions(
                $this->resourceWithInherited($productCategory),
                $this->buildPermissions($request->user(), $productCategory),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['productCategory' => $productCategory->id]);
        }
    }

    /**
     * POST /api/product-categories — create a new category.
     */
    public function store(StoreProductCategoryRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', ProductCategory::class);

            $productCategory = $this->service->create($request->toData());

            return $this->okWithPermissions(
                $this->resourceWithInherited($productCategory),
                $this->buildPermissions($request->user(), $productCategory),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/product-categories/{productCategory} — update an existing category.
     */
    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): JsonResponse
    {
        try {
            $this->authorize('update', $productCategory);

            $productCategory = $this->service->update($productCategory, $request->toData());

            return $this->okWithPermissions(
                $this->resourceWithInherited($productCategory),
                $this->buildPermissions($request->user(), $productCategory),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['productCategory' => $productCategory->id]);
        }
    }

    /**
     * POST /api/product-categories/bulk-move — move many categories under one
     * new parent, or to the root (spec 0063). All-or-nothing: a batch that
     * violates a rule (nested selection, cycle, business-function override)
     * is refused with the full conflict list and writes nothing.
     *
     * Every targeted category is authorized individually via
     * ProductCategoryPolicy (`product-categories.update`), mirroring
     * LeadController::assignOperators.
     */
    public function bulkMove(BulkMoveCategoriesRequest $request): JsonResponse
    {
        try {
            $categoryIds = $request->categoryIds();

            foreach (ProductCategory::query()->whereIn('id', $categoryIds)->get() as $category) {
                $this->authorize('update', $category);
            }

            $moved = $this->bulkMove->handle($categoryIds, $request->parentId());

            return $this->ok(['moved' => $moved], 'Categories moved');
        } catch (BulkMoveConflictException $conflict) {
            return $this->fail(
                $conflict->getMessage(),
                HttpStatusEnum::UNPROCESSABLE_ENTITY->value,
                ['reason' => $conflict->reason, 'conflicts' => $conflict->conflicts],
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * DELETE /api/product-categories/{productCategory} — delete a category.
     */
    public function destroy(ProductCategory $productCategory): JsonResponse
    {
        try {
            $this->authorize('delete', $productCategory);

            $this->service->delete($productCategory);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['productCategory' => $productCategory->id]);
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeEffectiveAttributes(User $actor): void
    {
        $allowed = $actor->can('product-categories.view')
            || $actor->can('products.view')
            || $actor->can('products.create')
            || $actor->can('products.update');

        if (! $allowed) {
            throw new AuthorizationException;
        }
    }

    /**
     * The ProductCategoryResource for $productCategory, resolved to a plain
     * array with its ancestors' attributes merged in as the sibling
     * `inherited_attributes` key — never into the Resource's own
     * `attributes`, which stays own-assignments-only. `resolve()` (not
     * `additional()`) because this array is nested inside the envelope's
     * `data` key rather than returned as the top-level HTTP response, and
     * `additional()` only merges on that top-level path.
     *
     * @return array<string, mixed>
     */
    private function resourceWithInherited(ProductCategory $productCategory): array
    {
        $productCategory->loadMissing('businessFunction');

        return array_merge(
            (new ProductCategoryResource($productCategory))->resolve(),
            [
                'inherited_attributes' => $this->service->inheritedAttributes($productCategory)->values(),
                'effective_business_function' => $this->service->effectiveBusinessFunction($productCategory),
            ],
        );
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?ProductCategory $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('product-categories'), $actor, $model);
    }
}

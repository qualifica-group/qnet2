<?php

namespace App\Http\Controllers\Products;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Products\StoreProductRequest;
use App\Http\Requests\Products\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `products` resource (spec 0017), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (ProductPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see ProductService
 */
class ProductController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ProductService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/products/{product} — single product (view row-action).
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        try {
            $this->authorize('view', $product);

            $product->loadMissing('category', 'vatRate', 'supplier');

            return $this->okWithPermissions(
                new ProductResource($product, $this->service->effectiveBusinessFunction($product), $this->service->applicableAttributes($product), $this->service->attributeLayout($product)),
                $this->buildPermissions($request->user(), $product),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['product' => $product->id]);
        }
    }

    /**
     * POST /api/products — create a new product.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Product::class);

            $product = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new ProductResource($product, $this->service->effectiveBusinessFunction($product), $this->service->applicableAttributes($product), $this->service->attributeLayout($product)),
                $this->buildPermissions($request->user(), $product),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/products/{product} — update an existing product.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        try {
            $this->authorize('update', $product);

            $product = $this->service->update($product, $request->toData());

            return $this->okWithPermissions(
                new ProductResource($product, $this->service->effectiveBusinessFunction($product), $this->service->applicableAttributes($product), $this->service->attributeLayout($product)),
                $this->buildPermissions($request->user(), $product),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['product' => $product->id]);
        }
    }

    /**
     * DELETE /api/products/{product} — delete a product.
     */
    public function destroy(Product $product): JsonResponse
    {
        try {
            $this->authorize('delete', $product);

            $this->service->delete($product);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['product' => $product->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Product $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('products'), $actor, $model);
    }
}

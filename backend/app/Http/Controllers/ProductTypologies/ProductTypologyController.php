<?php

namespace App\Http\Controllers\ProductTypologies;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\ProductTypologies\StoreProductTypologyRequest;
use App\Http\Requests\ProductTypologies\UpdateProductTypologyRequest;
use App\Http\Resources\ProductTypologyResource;
use App\Models\ProductTypology;
use App\Models\User;
use App\Services\ProductTypologyService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `product-typologies` resource (spec 0099), backing
 * the backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (ProductTypologyPolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see ProductTypologyService
 */
class ProductTypologyController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly ProductTypologyService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/product-typologies/{productTypology} — single typology (view
     * row-action).
     */
    public function show(Request $request, ProductTypology $productTypology): JsonResponse
    {
        try {
            $this->authorize('view', $productTypology);

            return $this->okWithPermissions(
                new ProductTypologyResource($productTypology),
                $this->buildPermissions($request->user(), $productTypology),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['productTypology' => $productTypology->id]);
        }
    }

    /**
     * POST /api/product-typologies — create a new typology.
     */
    public function store(StoreProductTypologyRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', ProductTypology::class);

            $productTypology = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new ProductTypologyResource($productTypology),
                $this->buildPermissions($request->user(), $productTypology),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/product-typologies/{productTypology} — update an
     * existing typology.
     */
    public function update(UpdateProductTypologyRequest $request, ProductTypology $productTypology): JsonResponse
    {
        try {
            $this->authorize('update', $productTypology);

            $productTypology = $this->service->update($productTypology, $request->toData());

            return $this->okWithPermissions(
                new ProductTypologyResource($productTypology),
                $this->buildPermissions($request->user(), $productTypology),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['productTypology' => $productTypology->id]);
        }
    }

    /**
     * DELETE /api/product-typologies/{productTypology} — delete a typology
     * (guarded by ProductTypologyService::delete(), spec 0099 D-8).
     */
    public function destroy(ProductTypology $productTypology): JsonResponse
    {
        try {
            $this->authorize('delete', $productTypology);

            $this->service->delete($productTypology);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['productTypology' => $productTypology->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?ProductTypology $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('product-typologies'), $actor, $model);
    }
}

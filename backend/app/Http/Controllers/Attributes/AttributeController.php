<?php

namespace App\Http\Controllers\Attributes;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Attributes\StoreAttributeRequest;
use App\Http\Requests\Attributes\UpdateAttributeRequest;
use App\Http\Resources\AttributeResource;
use App\Models\Attribute;
use App\Models\User;
use App\Services\AttributeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `attributes` resource (spec 0017), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (AttributePolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see AttributeService
 */
class AttributeController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AttributeService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/attributes/{attribute} — single attribute (view row-action).
     */
    public function show(Request $request, Attribute $attribute): JsonResponse
    {
        try {
            $this->authorize('view', $attribute);

            return $this->okWithPermissions(
                new AttributeResource($attribute->loadMissing(['options', 'currentAttribute'])),
                $this->buildPermissions($request->user(), $attribute),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['attribute' => $attribute->id]);
        }
    }

    /**
     * POST /api/attributes — create a new attribute.
     */
    public function store(StoreAttributeRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Attribute::class);

            $attribute = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new AttributeResource($attribute),
                $this->buildPermissions($request->user(), $attribute),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/attributes/{attribute} — update an existing attribute.
     */
    public function update(UpdateAttributeRequest $request, Attribute $attribute): JsonResponse
    {
        try {
            $this->authorize('update', $attribute);

            $attribute = $this->service->update($attribute, $request->toData());

            return $this->okWithPermissions(
                new AttributeResource($attribute),
                $this->buildPermissions($request->user(), $attribute),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['attribute' => $attribute->id]);
        }
    }

    /**
     * DELETE /api/attributes/{attribute} — delete an attribute.
     */
    public function destroy(Attribute $attribute): JsonResponse
    {
        try {
            $this->authorize('delete', $attribute);

            $this->service->delete($attribute);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['attribute' => $attribute->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Attribute $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('attributes'), $actor, $model);
    }
}

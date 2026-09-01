<?php

namespace App\Http\Controllers\UnitsOfMeasure;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\UnitsOfMeasure\StoreUnitOfMeasureRequest;
use App\Http\Requests\UnitsOfMeasure\UpdateUnitOfMeasureRequest;
use App\Http\Resources\UnitOfMeasureResource;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\UnitOfMeasureService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `units-of-measure` resource (spec 0088), backing
 * the backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (UnitOfMeasurePolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see UnitOfMeasureService
 */
class UnitOfMeasureController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly UnitOfMeasureService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/units-of-measure/{unitOfMeasure} — single unit of measure
     * (view row-action).
     */
    public function show(Request $request, UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        try {
            $this->authorize('view', $unitOfMeasure);

            return $this->okWithPermissions(
                new UnitOfMeasureResource($unitOfMeasure),
                $this->buildPermissions($request->user(), $unitOfMeasure),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['unitOfMeasure' => $unitOfMeasure->id]);
        }
    }

    /**
     * POST /api/units-of-measure — create a new unit of measure.
     */
    public function store(StoreUnitOfMeasureRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', UnitOfMeasure::class);

            $unitOfMeasure = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new UnitOfMeasureResource($unitOfMeasure),
                $this->buildPermissions($request->user(), $unitOfMeasure),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/units-of-measure/{unitOfMeasure} — update an existing
     * unit of measure.
     */
    public function update(UpdateUnitOfMeasureRequest $request, UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        try {
            $this->authorize('update', $unitOfMeasure);

            $unitOfMeasure = $this->service->update($unitOfMeasure, $request->toData());

            return $this->okWithPermissions(
                new UnitOfMeasureResource($unitOfMeasure),
                $this->buildPermissions($request->user(), $unitOfMeasure),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['unitOfMeasure' => $unitOfMeasure->id]);
        }
    }

    /**
     * DELETE /api/units-of-measure/{unitOfMeasure} — delete a unit of
     * measure (guarded by UnitOfMeasureService::delete(), spec 0088 D-7).
     */
    public function destroy(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        try {
            $this->authorize('delete', $unitOfMeasure);

            $this->service->delete($unitOfMeasure);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['unitOfMeasure' => $unitOfMeasure->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?UnitOfMeasure $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('units-of-measure'), $actor, $model);
    }
}

<?php

namespace App\Http\Controllers\AbsenceTypes;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AbsenceTypes\StoreAbsenceTypeRequest;
use App\Http\Requests\AbsenceTypes\UpdateAbsenceTypeRequest;
use App\Http\Resources\AbsenceTypeResource;
use App\Models\AbsenceType;
use App\Models\User;
use App\Services\AbsenceTypeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `absence-types` resource (spec 0278 D-5). Thin
 * controller: validation (FormRequest), authorization (AbsenceTypePolicy),
 * Service call, response with the `permissions` block (spec 0004).
 *
 * @see AbsenceTypeService
 */
class AbsenceTypeController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly AbsenceTypeService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function show(Request $request, AbsenceType $absenceType): JsonResponse
    {
        try {
            $this->authorize('view', $absenceType);

            return $this->withPermissions($request->user(), $absenceType);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['absenceType' => $absenceType->id]);
        }
    }

    public function store(StoreAbsenceTypeRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', AbsenceType::class);

            $absenceType = $this->service->create($request->toAttributes());

            return $this->withPermissions($request->user(), $absenceType, 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function update(UpdateAbsenceTypeRequest $request, AbsenceType $absenceType): JsonResponse
    {
        try {
            $this->authorize('update', $absenceType);

            $absenceType = $this->service->update($absenceType, $request->toAttributes());

            return $this->withPermissions($request->user(), $absenceType);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['absenceType' => $absenceType->id]);
        }
    }

    public function destroy(AbsenceType $absenceType): JsonResponse
    {
        try {
            $this->authorize('delete', $absenceType);

            $this->service->delete($absenceType);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['absenceType' => $absenceType->id]);
        }
    }

    private function withPermissions(User $actor, AbsenceType $absenceType, string $message = 'OK', HttpStatusEnum $status = HttpStatusEnum::OK): JsonResponse
    {
        return $this->okWithPermissions(
            (new AbsenceTypeResource($absenceType))->resolve(),
            $this->permissionsBuilder->build($this->authorization->resolve('absence-types'), $actor, $absenceType),
            $message,
            $status,
        );
    }
}

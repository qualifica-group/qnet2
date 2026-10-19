<?php

namespace App\Http\Controllers\AbsenceRequests;

use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AbsenceRequests\AbsenceRequestWriteRequest;
use App\Http\Resources\AbsenceRequestResource;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Services\Absences\Requests\AbsenceRequestService;
use App\Services\Absences\Requests\AbsenceRequestVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD of absence requests (spec 0278). A request the actor cannot see is a 404;
 * the list is the `absence-requests` table domain; the workflow actions live in
 * AbsenceRequestTransitionController.
 */
class AbsenceRequestController extends BaseApiController
{
    public function __construct(
        private readonly AbsenceRequestService $service,
        private readonly AbsenceRequestVisibility $visibility,
    ) {}

    /**
     * GET /api/absence-requests/{absenceRequest}
     */
    public function show(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        try {
            $this->visibility->assertCanView($request->user(), $absenceRequest);

            return $this->ok(new AbsenceRequestResource($this->service->detail($absenceRequest)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['absenceRequest' => $absenceRequest->id]);
        }
    }

    /**
     * POST /api/absence-requests
     */
    public function store(AbsenceRequestWriteRequest $request): JsonResponse
    {
        try {
            $created = $this->service->create(
                $request->toData(),
                User::query()->findOrFail($request->personId()),
                $request->user(),
                $request->shouldSubmit(),
            );

            return $this->ok(new AbsenceRequestResource($created), 'Created', HttpStatusEnum::CREATED);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PATCH /api/absence-requests/{absenceRequest} (draft only)
     */
    public function update(AbsenceRequestWriteRequest $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        try {
            $updated = $this->service->update($absenceRequest, $request->toData(), $request->user());

            return $this->ok(new AbsenceRequestResource($updated));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['absenceRequest' => $absenceRequest->id]);
        }
    }

    /**
     * DELETE /api/absence-requests/{absenceRequest} (draft only)
     */
    public function destroy(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        try {
            $this->visibility->assertCanView($request->user(), $absenceRequest);
            $this->service->delete($absenceRequest, $request->user());

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['absenceRequest' => $absenceRequest->id]);
        }
    }
}

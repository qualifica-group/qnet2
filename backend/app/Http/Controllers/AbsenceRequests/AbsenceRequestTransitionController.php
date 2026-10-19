<?php

namespace App\Http\Controllers\AbsenceRequests;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AbsenceRequests\AbsenceRequestRejectRequest;
use App\Http\Resources\AbsenceRequestResource;
use App\Models\AbsenceRequest;
use App\Services\Absences\Requests\AbsenceRequestService;
use App\Services\Absences\Requests\AbsenceRequestTransitions;
use App\Services\Absences\Requests\AbsenceRequestVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Workflow actions of an absence request (spec 0278, D-9): submit, approve,
 * reject, cancel. Each answers 404 when the request is not visible to the actor,
 * 403 when the actor may not perform it, 409 when the state does not allow it.
 */
class AbsenceRequestTransitionController extends BaseApiController
{
    public function __construct(
        private readonly AbsenceRequestService $service,
        private readonly AbsenceRequestTransitions $transitions,
        private readonly AbsenceRequestVisibility $visibility,
    ) {}

    /**
     * POST /api/absence-requests/{absenceRequest}/submit
     */
    public function submit(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        return $this->run($request, $absenceRequest, __FUNCTION__, fn (): AbsenceRequest => $this->service->submit($absenceRequest, $request->user()));
    }

    /**
     * POST /api/absence-requests/{absenceRequest}/approve
     */
    public function approve(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        return $this->run($request, $absenceRequest, __FUNCTION__, fn (): AbsenceRequest => $this->transitions->approve($absenceRequest, $request->user()));
    }

    /**
     * POST /api/absence-requests/{absenceRequest}/reject
     */
    public function reject(AbsenceRequestRejectRequest $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        return $this->run($request, $absenceRequest, __FUNCTION__, fn (): AbsenceRequest => $this->transitions->reject($absenceRequest, $request->user(), $request->reason()));
    }

    /**
     * POST /api/absence-requests/{absenceRequest}/cancel
     */
    public function cancel(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        return $this->run($request, $absenceRequest, __FUNCTION__, fn (): AbsenceRequest => $this->transitions->cancel($absenceRequest, $request->user()));
    }

    /**
     * @param  callable(): AbsenceRequest  $transition
     */
    private function run(Request $request, AbsenceRequest $absenceRequest, string $method, callable $transition): JsonResponse
    {
        try {
            $this->visibility->assertCanView($request->user(), $absenceRequest);

            return $this->ok(new AbsenceRequestResource($transition()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, $method, ['absenceRequest' => $absenceRequest->id]);
        }
    }
}

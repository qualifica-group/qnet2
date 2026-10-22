<?php

namespace App\Http\Controllers\AbsenceRequests;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AbsenceRequests\AbsenceRequestPreviewRequest;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Services\Absences\Insight\AbsenceInsightBuilder;
use App\Services\Absences\Requests\AbsenceRequestVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Insight of an absence request (spec 0304): the preview of a payload before
 * sending it and the context of a stored request for who decides it.
 */
class AbsenceInsightController extends BaseApiController
{
    public function __construct(
        private readonly AbsenceInsightBuilder $insight,
        private readonly AbsenceRequestVisibility $visibility,
    ) {}

    /**
     * POST /api/absence-requests/preview
     */
    public function preview(AbsenceRequestPreviewRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->insight->forPayload(
                $request->user(),
                User::query()->findOrFail($request->personId()),
                $request->toData(),
                $request->draftId(),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/absence-requests/{absenceRequest}/insight
     */
    public function show(Request $request, AbsenceRequest $absenceRequest): JsonResponse
    {
        try {
            $this->visibility->assertCanView($request->user(), $absenceRequest);

            return $this->ok($this->insight->forRequest($request->user(), $absenceRequest));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['absenceRequest' => $absenceRequest->id]);
        }
    }
}

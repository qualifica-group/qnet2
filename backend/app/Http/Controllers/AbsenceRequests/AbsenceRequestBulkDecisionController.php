<?php

namespace App\Http\Controllers\AbsenceRequests;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\AbsenceRequests\AbsenceRequestBulkDecisionRequest;
use App\Services\Absences\Requests\AbsenceRequestBulkDecision;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/absence-requests/bulk-decision (spec 0304 D-17): per-id outcome, a
 * failing id never blocks the others.
 */
class AbsenceRequestBulkDecisionController extends BaseApiController
{
    public function __construct(private readonly AbsenceRequestBulkDecision $bulk) {}

    public function __invoke(AbsenceRequestBulkDecisionRequest $request): JsonResponse
    {
        try {
            return $this->ok($this->bulk->decide(
                $request->user(),
                $request->string('action')->toString(),
                $request->ids(),
                $request->input('rejection_reason'),
            ));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}

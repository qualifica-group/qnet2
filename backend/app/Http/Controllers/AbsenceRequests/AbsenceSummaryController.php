<?php

namespace App\Http\Controllers\AbsenceRequests;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\AbsenceRequest;
use App\Services\Absences\Insight\AbsenceSummaryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * GET /api/absences/summary (spec 0304 D-18): counters and lists for the
 * Absences page, the menu badge and the dashboard block.
 */
class AbsenceSummaryController extends BaseApiController
{
    public function __construct(private readonly AbsenceSummaryBuilder $summary) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            Gate::forUser($request->user())->authorize('viewAny', AbsenceRequest::class);

            return $this->ok($this->summary->build($request->user()));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}

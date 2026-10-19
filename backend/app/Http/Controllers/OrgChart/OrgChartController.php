<?php

namespace App\Http\Controllers\OrgChart;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\User;
use App\Services\OrgChart\OrgChartReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Read endpoints of the org chart (spec 0273 D-2/D-3), gated by
 * `org-chart.view`. Thin: authorize, delegate to OrgChartReadService, respond.
 */
class OrgChartController extends BaseApiController
{
    public const string VIEW_PERMISSION = 'org-chart.view';

    public function __construct(private readonly OrgChartReadService $service) {}

    /**
     * GET /api/org-chart/functions
     */
    public function functions(Request $request): JsonResponse
    {
        try {
            $this->authorizeView($request);

            return $this->ok($this->service->functions());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/org-chart/people
     */
    public function people(Request $request): JsonResponse
    {
        try {
            $this->authorizeView($request);

            return $this->ok($this->service->people());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    private function authorizeView(Request $request): void
    {
        /** @var User $actor */
        $actor = $request->user();

        abort_unless($actor->can(self::VIEW_PERMISSION), 403);
    }
}

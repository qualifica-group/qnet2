<?php

declare(strict_types=1);

namespace App\Http\Controllers\TimeEntries;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TimeEntries\TimeEntryTeamStatsRequest;
use App\Http\Resources\TimeEntryTeamMemberResource;
use App\Models\User;
use App\Services\TimeEntries\TimeEntryTeamPulseService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET stats/team (spec 0122, D-10, AC-019/AC-020). Same story as
 * TimeEntryStatsController: authorization runs inside the Service (there is
 * no single TimeEntry record for a Policy to authorize against — this reads
 * `time-entries.viewAny`/`viewAll` resource-level, not per-record).
 *
 * @see TimeEntryTeamPulseService
 */
class TimeEntryTeamStatsController extends BaseApiController
{
    public function __construct(private readonly TimeEntryTeamPulseService $service) {}

    public function __invoke(TimeEntryTeamStatsRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            $result = $this->service->handle($request->filter(), $actor);

            return $this->ok([
                'is_full_list' => $result['is_full_list'],
                'items' => TimeEntryTeamMemberResource::collection($result['items']),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}

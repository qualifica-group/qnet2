<?php

declare(strict_types=1);

namespace App\Http\Controllers\TimeEntries;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TimeEntries\TimeEntryStatsRequest;
use App\Models\User;
use App\Services\TimeEntries\TimeEntryStatsService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * GET stats/overview and GET stats/pulse (spec 0122, D-11, AC-017/AC-018).
 * Same authorization story as `TimeEntryController::index()`: rule R
 * (`TimeEntryReadAuthorizer`) runs inside the Service, not here — there is
 * no single TimeEntry record for a Policy to authorize against.
 *
 * @see TimeEntryStatsService
 */
class TimeEntryStatsController extends BaseApiController
{
    public function __construct(private readonly TimeEntryStatsService $service) {}

    public function overview(TimeEntryStatsRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            return $this->ok($this->service->overview($request->filter(), $actor));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    public function pulse(TimeEntryStatsRequest $request): JsonResponse
    {
        try {
            /** @var User $actor */
            $actor = $request->user();

            return $this->ok($this->service->pulse($request->filter(), $actor));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}

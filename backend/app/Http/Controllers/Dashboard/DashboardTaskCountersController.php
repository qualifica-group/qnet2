<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskDashboardCounters;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/dashboard/tasks (spec 0151): the Task counters of the `/dashboard`
 * home. Thin by construction — App\Services\Tasks\TaskDashboardCounters owns
 * every query. `tasks.viewAny` is the same gate the `tasks` grid itself
 * requires (AbstractTableDefinition's fail-safe default), so a card is never
 * visible to an actor who could not open the list it links to.
 */
class DashboardTaskCountersController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskDashboardCounters $counters) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Task::class);

            /** @var User $actor */
            $actor = $request->user();

            return $this->ok($this->counters->handle($actor));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }
}

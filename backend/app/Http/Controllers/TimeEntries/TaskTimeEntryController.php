<?php

declare(strict_types=1);

namespace App\Http\Controllers\TimeEntries;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TimeEntries\StoreTaskTimeEntryRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Tasks\TaskAbilityResolver;
use App\Services\TimeEntries\TimeEntryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Task-scoped segnatempo endpoints (spec 0122, D-9): the "Segnatempo"
 * section of the Task detail. Lives in the TimeEntries controller
 * namespace, not Tasks (see routes/api/tasks.php's own comment on why) —
 * TaskController/TaskResource stay untouched by this spec.
 *
 * Both actions require the D-9 visibility scope on $task (`view`,
 * TaskPolicy) PLUS a `time-entries.*` permission; `store()` additionally
 * requires `TaskAbilityResolver::canComplete()` — the SAME record-role
 * check D-9 uses for "registra chi ha `time-entries.create` E
 * `canComplete()` sul Task", read here rather than re-derived.
 *
 * @see TimeEntryService
 */
class TaskTimeEntryController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly TimeEntryService $service) {}

    /**
     * GET /api/tasks/{task}/time-entries — every user's entries on this
     * Task (D-9), newest first.
     */
    public function index(Request $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('view', $task);

            /** @var User $actor */
            $actor = $request->user();
            abort_unless($actor->can('time-entries.viewAny'), 403);

            $items = TimeEntry::query()
                ->where('task_id', $task->id)
                ->with(['user', 'taskType', 'registry', 'opportunity', 'workOrder', 'task'])
                ->orderByDesc('date')
                ->orderByDesc('start_time')
                ->orderByDesc('id')
                ->get();

            return $this->ok([
                'total_minutes' => (int) $items->sum('minutes'),
                'can_create' => $this->canCreate($actor, $task),
                'items' => TimeEntryResource::collection($items),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['task' => $task->id]);
        }
    }

    /**
     * POST /api/tasks/{task}/time-entries — the editor "Nuovo intervallo":
     * owner is always the authenticated actor, title and the three record
     * links are always the Task's own (D-5/D-9).
     */
    public function store(StoreTaskTimeEntryRequest $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('view', $task);

            /** @var User $actor */
            $actor = $request->user();
            abort_unless($this->canCreate($actor, $task), 403);

            $entry = $this->service->create($request->toData($task->id), $actor);

            return $this->created(new TimeEntryResource($entry));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['task' => $task->id]);
        }
    }

    private function canCreate(User $actor, Task $task): bool
    {
        return $actor->can('time-entries.create') && TaskAbilityResolver::canComplete($actor, $task);
    }
}

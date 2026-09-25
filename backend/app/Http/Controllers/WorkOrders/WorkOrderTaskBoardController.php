<?php

namespace App\Http\Controllers\WorkOrders;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TaskBoard\BulkTaskBoardRequest;
use App\Http\Requests\TaskBoard\MoveTaskBoardRequest;
use App\Http\Resources\BoardTaskResource;
use App\Http\Resources\WorkOrderStageResource;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use App\Services\WorkOrders\TaskBoardQuery;
use App\Services\WorkOrders\TaskBulkActionService;
use App\Services\WorkOrders\TaskStagePositioner;
use App\Services\WorkOrders\WorkOrderClosedGuard;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * The Task board itself (spec 0146): the read endpoint every list/kanban
 * view hydrates from, and the drag-and-drop `move`.
 *
 * `show()` combines two gates the data_contract names explicitly (D-9):
 * `view` on the commessa (WorkOrderPolicy — carries D-9's own membership
 * scope) AND `tasks.viewAny`. `is_read_only` is derived from the SAME
 * `update` ability the stage endpoints gate their mutations on, so a board
 * that says "read-only" and a stage mutation that 409s never disagree.
 *
 * `move()` authorizes on the TASK, not the commessa (D-9: `TaskPolicy::
 * update`, which already carries `TaskAbilityResolver::canUpdate` plus the
 * D-9 visibility scope) — the one endpoint in this module whose 403 is
 * per-record rather than resource-level.
 *
 * `show()` also carries the per-fase segnatempo totals (spec 0163, D-4/
 * AC-007): each stage's own `logged_minutes` plus the commessa-level
 * `unstaged_logged_minutes` — this endpoint is spec 0163's chosen home for
 * them (data_contract: "l'endpoint esatto fissato in esecuzione ... senza
 * nuovo endpoint se quello esistente basta"), reusing the SAME `stages` read
 * this response already returns rather than adding a new one.
 *
 * `bulk()` (spec 0146, D-7) authorizes only `view` on the commessa here: the
 * per-task ability (`canUpdate`/`canComplete`/`canBlock`) is re-asserted
 * PER ROW by `TaskBulkActionService`, which is also where a single task's
 * failure becomes `{ok: false, message}` without aborting the others.
 *
 * @see TaskBoardQuery
 * @see TaskStagePositioner
 * @see TaskBulkActionService
 */
class WorkOrderTaskBoardController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskBoardQuery $boardQuery,
        private readonly TaskStagePositioner $positioner,
        private readonly TaskBulkActionService $bulkActionService,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/work-orders/{workOrder}/task-board.
     */
    public function show(Request $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('view', $workOrder);
            $this->authorize('viewAny', Task::class);

            /** @var User $actor */
            $actor = $request->user();

            $tasksAuthorization = $this->authorization->resolve('tasks');
            $tasks = $this->boardQuery->tasks($workOrder, $actor)
                ->map(fn (Task $task): BoardTaskResource => new BoardTaskResource(
                    $task,
                    $this->permissionsBuilder->build($tasksAuthorization, $actor, $task),
                ))
                ->values();

            return $this->ok([
                'stages' => WorkOrderStageResource::collection($this->boardQuery->stages($workOrder)),
                'tasks' => $tasks,
                'is_read_only' => $workOrder->is_force_closed || ! $actor->can('update', $workOrder),
                'unstaged_logged_minutes' => $this->boardQuery->unstagedLoggedMinutes($workOrder),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * POST /api/work-orders/{workOrder}/task-board/move (AC-013/AC-014).
     */
    public function move(MoveTaskBoardRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            /** @var Task $task */
            $task = Task::query()->findOrFail($request->taskId());

            $this->authorize('update', $task);

            WorkOrderClosedGuard::assertOpen($workOrder);

            abort_if($task->work_order_id !== $workOrder->id, 422, 'This task does not belong to this commessa.');
            abort_if($task->parent_task_id !== null, 422, 'A sub-task has no stage of its own.');

            $destinationStage = $this->resolveDestinationStage($workOrder, $request->workOrderStageId());

            $rows = $this->positioner->move($workOrder, $task, $destinationStage, $request->position());

            return $this->ok(['tasks' => $rows]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * POST /api/work-orders/{workOrder}/task-board/bulk (D-7/AC-017..AC-020).
     */
    public function bulk(BulkTaskBoardRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('view', $workOrder);

            /** @var User $actor */
            $actor = $request->user();

            return $this->ok($this->bulkActionService->execute($workOrder, $request->toData(), $actor));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * Resolves and validates the move's destination stage (AC-014): null
     * stays "Senza fase"; otherwise the stage must belong to THIS commessa
     * (422) and must not be closed (409) — checked here rather than in the
     * FormRequest, which only knows the row exists at all, not whose
     * commessa it belongs to.
     */
    private function resolveDestinationStage(WorkOrder $workOrder, ?int $stageId): ?WorkOrderStage
    {
        if ($stageId === null) {
            return null;
        }

        /** @var WorkOrderStage $stage */
        $stage = WorkOrderStage::query()->findOrFail($stageId);

        abort_if($stage->work_order_id !== $workOrder->id, 422, 'The destination stage does not belong to this commessa.');
        abort_if($stage->isClosed(), 409, 'This stage is closed.');

        return $stage;
    }
}

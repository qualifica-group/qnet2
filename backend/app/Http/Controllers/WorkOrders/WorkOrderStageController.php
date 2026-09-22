<?php

namespace App\Http\Controllers\WorkOrders;

use App\Exceptions\WorkOrders\WorkOrderStageHasOpenTasksException;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrderStages\ReorderWorkOrderStagesRequest;
use App\Http\Requests\WorkOrderStages\StoreWorkOrderStageRequest;
use App\Http\Requests\WorkOrderStages\UpdateWorkOrderStageRequest;
use App\Http\Resources\WorkOrderStageResource;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use App\Services\WorkOrders\WorkOrderStageService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD + lifecycle endpoints for a commessa's own "Fasi" (spec 0146,
 * D-2/D-4/D-9): index/store/update/destroy/reorder/close/reopen.
 *
 * Thin controller: every endpoint is gated on the OWNING commessa
 * (WorkOrderPolicy — `view` for the read, `update` for every mutation, D-9),
 * never a Policy of the stage's own. `Route::scopeBindings()`
 * (routes/api/work-order-task-board.php) is what turns a `{stage}` from
 * another commessa into a 404 before this class ever runs.
 *
 * `close()` carries its own catch for `WorkOrderStageHasOpenTasksException`
 * (D-4/AC-008): the one 409 in this module whose body carries `data.
 * open_tasks_count`, ahead of the generic Throwable handler which cannot
 * express that shape.
 *
 * @see WorkOrderStageService
 */
class WorkOrderStageController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly WorkOrderStageService $service) {}

    /**
     * GET /api/work-orders/{workOrder}/stages — the option list the task
     * form's "Fase" select consumes (data_contract).
     */
    public function index(WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('view', $workOrder);

            return $this->ok(WorkOrderStageResource::collection($this->service->list($workOrder)));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * POST /api/work-orders/{workOrder}/stages — create a new stage, accoded
     * at the end (D-2).
     */
    public function store(StoreWorkOrderStageRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('update', $workOrder);

            $stage = $this->service->create($workOrder, $request->name());

            return $this->created(new WorkOrderStageResource($stage));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * PATCH /api/work-orders/{workOrder}/stages/{stage} — rename.
     */
    public function update(UpdateWorkOrderStageRequest $request, WorkOrder $workOrder, WorkOrderStage $stage): JsonResponse
    {
        try {
            $this->authorize('update', $workOrder);

            $stage = $this->service->rename($workOrder, $stage, $request->name());

            return $this->ok(new WorkOrderStageResource($stage));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id, 'stage' => $stage->id]);
        }
    }

    /**
     * DELETE /api/work-orders/{workOrder}/stages/{stage} — the frozen
     * contract answers 200/data:null here, NOT the module's usual 204
     * (WorkOrderService::delete()'s own convention): the stage's tasks
     * demotion is the meaningful side effect, not a resource disappearing
     * with nothing left to say about it.
     */
    public function destroy(WorkOrder $workOrder, WorkOrderStage $stage): JsonResponse
    {
        try {
            $this->authorize('update', $workOrder);

            $this->service->delete($workOrder, $stage);

            return $this->ok();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id, 'stage' => $stage->id]);
        }
    }

    /**
     * POST /api/work-orders/{workOrder}/stages/reorder — resequence every
     * stage to the submitted order (AC-006).
     */
    public function reorder(ReorderWorkOrderStagesRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('update', $workOrder);

            $stages = $this->service->reorder($workOrder, $request->stageIds());

            return $this->ok(WorkOrderStageResource::collection($stages));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }

    /**
     * POST /api/work-orders/{workOrder}/stages/{stage}/close (D-4/AC-008).
     */
    public function close(Request $request, WorkOrder $workOrder, WorkOrderStage $stage): JsonResponse
    {
        try {
            $this->authorize('update', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $stage = $this->service->close($workOrder, $stage, $actor);

            return $this->ok(new WorkOrderStageResource($stage));
        } catch (WorkOrderStageHasOpenTasksException $exception) {
            return $this->fail(__($exception->getMessage()), 409, data: ['open_tasks_count' => $exception->openTasksCount()]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id, 'stage' => $stage->id]);
        }
    }

    /**
     * POST /api/work-orders/{workOrder}/stages/{stage}/reopen (D-4).
     */
    public function reopen(WorkOrder $workOrder, WorkOrderStage $stage): JsonResponse
    {
        try {
            $this->authorize('update', $workOrder);

            $stage = $this->service->reopen($workOrder, $stage);

            return $this->ok(new WorkOrderStageResource($stage));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id, 'stage' => $stage->id]);
        }
    }
}

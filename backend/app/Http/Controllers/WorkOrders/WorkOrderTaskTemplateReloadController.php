<?php

declare(strict_types=1);

namespace App\Http\Controllers\WorkOrders;

use App\Exceptions\WorkOrders\WorkOrderTaskTemplateReloadBlockedException;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\WorkOrders\ReloadWorkOrderTaskTemplateRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderTaskTemplateReloader;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/work-orders/{workOrder}/task-template/reload (spec 0399): loads
 * or reloads the commessa's Modello di Task, replacing the tasks of the
 * previous one. 409 with `data.completed_tasks_count` when one of them is
 * already completed.
 */
class WorkOrderTaskTemplateReloadController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly WorkOrderTaskTemplateReloader $reloader) {}

    public function __invoke(ReloadWorkOrderTaskTemplateRequest $request, WorkOrder $workOrder): JsonResponse
    {
        try {
            $this->authorize('manage', $workOrder);

            /** @var User $actor */
            $actor = $request->user();
            $template = TaskTemplate::query()->findOrFail($request->taskTemplateId());
            $workOrder = $this->reloader->handle($workOrder, $template, $actor);

            return $this->ok(new WorkOrderResource($workOrder), 'Task template loaded.');
        } catch (WorkOrderTaskTemplateReloadBlockedException $exception) {
            return $this->fail(__($exception->getMessage()), 409, data: ['completed_tasks_count' => $exception->completedTasksCount()]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['workOrder' => $workOrder->id]);
        }
    }
}

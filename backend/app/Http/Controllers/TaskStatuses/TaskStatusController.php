<?php

declare(strict_types=1);

namespace App\Http\Controllers\TaskStatuses;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Requests\TaskStatuses\StoreTaskStatusRequest;
use App\Http\Requests\TaskStatuses\UpdateTaskStatusRequest;
use App\Http\Resources\TaskStatusResource;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\TaskStatusService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `task-statuses` resource (spec 0101), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (TaskStatusPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see TaskStatusService
 */
class TaskStatusController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskStatusService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/task-statuses/{taskStatus} — single task status (view row-action).
     */
    public function show(Request $request, TaskStatus $taskStatus): JsonResponse
    {
        try {
            $this->authorize('view', $taskStatus);

            return $this->okWithPermissions(
                new TaskStatusResource($taskStatus),
                $this->buildPermissions($request->user(), $taskStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskStatus' => $taskStatus->id]);
        }
    }

    /**
     * POST /api/task-statuses — create a new task status.
     */
    public function store(StoreTaskStatusRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', TaskStatus::class);

            $taskStatus = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new TaskStatusResource($taskStatus),
                $this->buildPermissions($request->user(), $taskStatus),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/task-statuses/{taskStatus} — update an existing task status.
     */
    public function update(UpdateTaskStatusRequest $request, TaskStatus $taskStatus): JsonResponse
    {
        try {
            $this->authorize('update', $taskStatus);

            $taskStatus = $this->service->update($taskStatus, $request->toData());

            return $this->okWithPermissions(
                new TaskStatusResource($taskStatus),
                $this->buildPermissions($request->user(), $taskStatus),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskStatus' => $taskStatus->id]);
        }
    }

    /**
     * DELETE /api/task-statuses/{taskStatus} — delete a task status (422 on a system row, 409 when referenced by a Task,
     * spec 0101 D-8).
     */
    public function destroy(TaskStatus $taskStatus): JsonResponse
    {
        try {
            $this->authorize('delete', $taskStatus);

            $this->service->delete($taskStatus);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskStatus' => $taskStatus->id]);
        }
    }

    /**
     * POST /api/task-statuses/reorder — resequence the custom rows
     * (AC-047). Gated on `task-statuses.update` directly: no single Model
     * instance exists for a bulk reorder, so there is no Policy
     * `update($user, $model)` to delegate to — mirrors
     * ContractStatusController::reorder.
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('task-statuses.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (TaskStatus $status): array => [
                'id' => $status->id,
                'sort_order' => $status->sort_order,
                'system_key' => $status->system_key,
            ])->all());
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?TaskStatus $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('task-statuses'), $actor, $model);
    }
}

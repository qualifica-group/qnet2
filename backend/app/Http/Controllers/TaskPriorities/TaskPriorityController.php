<?php

declare(strict_types=1);

namespace App\Http\Controllers\TaskPriorities;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Requests\TaskPriorities\StoreTaskPriorityRequest;
use App\Http\Requests\TaskPriorities\UpdateTaskPriorityRequest;
use App\Http\Resources\TaskPriorityResource;
use App\Models\TaskPriority;
use App\Models\User;
use App\Services\TaskPriorityService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `task-priorities` resource (spec 0101), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (TaskPriorityPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see TaskPriorityService
 */
class TaskPriorityController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskPriorityService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/task-priorities/{taskPriority} — single task priority (view row-action).
     */
    public function show(Request $request, TaskPriority $taskPriority): JsonResponse
    {
        try {
            $this->authorize('view', $taskPriority);

            return $this->okWithPermissions(
                new TaskPriorityResource($taskPriority),
                $this->buildPermissions($request->user(), $taskPriority),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskPriority' => $taskPriority->id]);
        }
    }

    /**
     * POST /api/task-priorities — create a new task priority.
     */
    public function store(StoreTaskPriorityRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', TaskPriority::class);

            $taskPriority = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new TaskPriorityResource($taskPriority),
                $this->buildPermissions($request->user(), $taskPriority),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/task-priorities/{taskPriority} — update an existing task priority.
     */
    public function update(UpdateTaskPriorityRequest $request, TaskPriority $taskPriority): JsonResponse
    {
        try {
            $this->authorize('update', $taskPriority);

            $taskPriority = $this->service->update($taskPriority, $request->toData());

            return $this->okWithPermissions(
                new TaskPriorityResource($taskPriority),
                $this->buildPermissions($request->user(), $taskPriority),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskPriority' => $taskPriority->id]);
        }
    }

    /**
     * DELETE /api/task-priorities/{taskPriority} — delete a task priority (409 when referenced by a Task,
     * spec 0101 D-8).
     */
    public function destroy(TaskPriority $taskPriority): JsonResponse
    {
        try {
            $this->authorize('delete', $taskPriority);

            $this->service->delete($taskPriority);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskPriority' => $taskPriority->id]);
        }
    }

    /**
     * POST /api/task-priorities/reorder — resequence the rows (AC-049). Gated on
     * `task-priorities.update` directly: no single Model instance exists for a bulk
     * reorder, so there is no Policy `update($user, $model)` to delegate to
     * — mirrors TaskStatusController::reorder.
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('task-priorities.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (TaskPriority $taskPriority): array => [
                'id' => $taskPriority->id,
                'sort_order' => $taskPriority->sort_order,
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
    private function buildPermissions(User $actor, ?TaskPriority $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('task-priorities'), $actor, $model);
    }
}

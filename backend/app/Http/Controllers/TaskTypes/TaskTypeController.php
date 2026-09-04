<?php

declare(strict_types=1);

namespace App\Http\Controllers\TaskTypes;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Requests\TaskTypes\StoreTaskTypeRequest;
use App\Http\Requests\TaskTypes\UpdateTaskTypeRequest;
use App\Http\Resources\TaskTypeResource;
use App\Models\TaskType;
use App\Models\User;
use App\Services\TaskTypeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `task-types` resource (spec 0101), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (TaskTypePolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see TaskTypeService
 */
class TaskTypeController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskTypeService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/task-types/{taskType} — single task type (view row-action).
     */
    public function show(Request $request, TaskType $taskType): JsonResponse
    {
        try {
            $this->authorize('view', $taskType);

            return $this->okWithPermissions(
                new TaskTypeResource($taskType),
                $this->buildPermissions($request->user(), $taskType),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskType' => $taskType->id]);
        }
    }

    /**
     * POST /api/task-types — create a new task type.
     */
    public function store(StoreTaskTypeRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', TaskType::class);

            $taskType = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new TaskTypeResource($taskType),
                $this->buildPermissions($request->user(), $taskType),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/task-types/{taskType} — update an existing task type.
     */
    public function update(UpdateTaskTypeRequest $request, TaskType $taskType): JsonResponse
    {
        try {
            $this->authorize('update', $taskType);

            $taskType = $this->service->update($taskType, $request->toData());

            return $this->okWithPermissions(
                new TaskTypeResource($taskType),
                $this->buildPermissions($request->user(), $taskType),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskType' => $taskType->id]);
        }
    }

    /**
     * DELETE /api/task-types/{taskType} — delete a task type (409 when referenced by a Task,
     * spec 0101 D-8).
     */
    public function destroy(TaskType $taskType): JsonResponse
    {
        try {
            $this->authorize('delete', $taskType);

            $this->service->delete($taskType);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskType' => $taskType->id]);
        }
    }

    /**
     * POST /api/task-types/reorder — resequence the rows (AC-049). Gated on
     * `task-types.update` directly: no single Model instance exists for a bulk
     * reorder, so there is no Policy `update($user, $model)` to delegate to
     * — mirrors TaskStatusController::reorder.
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('task-types.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (TaskType $taskType): array => [
                'id' => $taskType->id,
                'sort_order' => $taskType->sort_order,
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
    private function buildPermissions(User $actor, ?TaskType $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('task-types'), $actor, $model);
    }
}

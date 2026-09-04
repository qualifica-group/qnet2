<?php

namespace App\Http\Controllers\Tasks;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `tasks` resource (spec 0101), backing the
 * backend-driven table row-actions (view/delete) plus create/update.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (TaskPolicy — which carries the D-9 visibility scoping on view/update/
 * delete), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see TaskService
 */
class TaskController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/tasks/{task} — single Task (view row-action). 403 both without
     * `tasks.view` and for a Task outside the actor's visibility scope
     * (AC-060).
     */
    public function show(Request $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('view', $task);

            $task = $this->service->loadDetail($task);

            return $this->okWithPermissions(
                new TaskResource($task),
                $this->buildPermissions($request->user(), $task),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['task' => $task->id]);
        }
    }

    /**
     * POST /api/tasks — create a new Task. `creator_id` is taken from the
     * authenticated actor (D-10), never from the payload.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Task::class);

            /** @var User $actor */
            $actor = $request->user();
            $task = $this->service->create($request->toData(), $actor);

            return $this->okWithPermissions(
                new TaskResource($task),
                $this->buildPermissions($actor, $task),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/tasks/{task} — update an existing Task.
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('update', $task);

            $task = $this->service->update($task, $request->toData());

            return $this->okWithPermissions(
                new TaskResource($task),
                $this->buildPermissions($request->user(), $task),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['task' => $task->id]);
        }
    }

    /**
     * DELETE /api/tasks/{task} — delete a Task, unless it has sub-tasks
     * (TaskService::delete(), D-8a: 409).
     */
    public function destroy(Task $task): JsonResponse
    {
        try {
            $this->authorize('delete', $task);

            $this->service->delete($task);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['task' => $task->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Task $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('tasks'), $actor, $model);
    }
}

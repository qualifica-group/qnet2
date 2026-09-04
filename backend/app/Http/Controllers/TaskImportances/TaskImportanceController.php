<?php

declare(strict_types=1);

namespace App\Http\Controllers\TaskImportances;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Requests\TaskImportances\StoreTaskImportanceRequest;
use App\Http\Requests\TaskImportances\UpdateTaskImportanceRequest;
use App\Http\Resources\TaskImportanceResource;
use App\Models\TaskImportance;
use App\Models\User;
use App\Services\TaskImportanceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `task-importances` resource (spec 0101), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (TaskImportancePolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see TaskImportanceService
 */
class TaskImportanceController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskImportanceService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/task-importances/{taskImportance} — single task importance (view row-action).
     */
    public function show(Request $request, TaskImportance $taskImportance): JsonResponse
    {
        try {
            $this->authorize('view', $taskImportance);

            return $this->okWithPermissions(
                new TaskImportanceResource($taskImportance),
                $this->buildPermissions($request->user(), $taskImportance),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskImportance' => $taskImportance->id]);
        }
    }

    /**
     * POST /api/task-importances — create a new task importance.
     */
    public function store(StoreTaskImportanceRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', TaskImportance::class);

            $taskImportance = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new TaskImportanceResource($taskImportance),
                $this->buildPermissions($request->user(), $taskImportance),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/task-importances/{taskImportance} — update an existing task importance.
     */
    public function update(UpdateTaskImportanceRequest $request, TaskImportance $taskImportance): JsonResponse
    {
        try {
            $this->authorize('update', $taskImportance);

            $taskImportance = $this->service->update($taskImportance, $request->toData());

            return $this->okWithPermissions(
                new TaskImportanceResource($taskImportance),
                $this->buildPermissions($request->user(), $taskImportance),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskImportance' => $taskImportance->id]);
        }
    }

    /**
     * DELETE /api/task-importances/{taskImportance} — delete a task importance (409 when referenced by a Task,
     * spec 0101 D-8).
     */
    public function destroy(TaskImportance $taskImportance): JsonResponse
    {
        try {
            $this->authorize('delete', $taskImportance);

            $this->service->delete($taskImportance);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskImportance' => $taskImportance->id]);
        }
    }

    /**
     * POST /api/task-importances/reorder — resequence the rows (AC-049). Gated on
     * `task-importances.update` directly: no single Model instance exists for a bulk
     * reorder, so there is no Policy `update($user, $model)` to delegate to
     * — mirrors TaskStatusController::reorder.
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('task-importances.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (TaskImportance $taskImportance): array => [
                'id' => $taskImportance->id,
                'sort_order' => $taskImportance->sort_order,
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
    private function buildPermissions(User $actor, ?TaskImportance $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('task-importances'), $actor, $model);
    }
}

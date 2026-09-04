<?php

declare(strict_types=1);

namespace App\Http\Controllers\TaskCategories;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Statuses\ReorderStatusesRequest;
use App\Http\Requests\TaskCategories\StoreTaskCategoryRequest;
use App\Http\Requests\TaskCategories\UpdateTaskCategoryRequest;
use App\Http\Resources\TaskCategoryResource;
use App\Models\TaskCategory;
use App\Models\User;
use App\Services\TaskCategoryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `task-categories` resource (spec 0101), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (TaskCategoryPolicy), Service call, response. No business logic, no queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see TaskCategoryService
 */
class TaskCategoryController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskCategoryService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/task-categories/{taskCategory} — single task category (view row-action).
     */
    public function show(Request $request, TaskCategory $taskCategory): JsonResponse
    {
        try {
            $this->authorize('view', $taskCategory);

            return $this->okWithPermissions(
                new TaskCategoryResource($taskCategory),
                $this->buildPermissions($request->user(), $taskCategory),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskCategory' => $taskCategory->id]);
        }
    }

    /**
     * POST /api/task-categories — create a new task category.
     */
    public function store(StoreTaskCategoryRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', TaskCategory::class);

            $taskCategory = $this->service->create($request->toData());

            return $this->okWithPermissions(
                new TaskCategoryResource($taskCategory),
                $this->buildPermissions($request->user(), $taskCategory),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/task-categories/{taskCategory} — update an existing task category.
     */
    public function update(UpdateTaskCategoryRequest $request, TaskCategory $taskCategory): JsonResponse
    {
        try {
            $this->authorize('update', $taskCategory);

            $taskCategory = $this->service->update($taskCategory, $request->toData());

            return $this->okWithPermissions(
                new TaskCategoryResource($taskCategory),
                $this->buildPermissions($request->user(), $taskCategory),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskCategory' => $taskCategory->id]);
        }
    }

    /**
     * DELETE /api/task-categories/{taskCategory} — delete a task category (409 when referenced by a Task,
     * spec 0101 D-8).
     */
    public function destroy(TaskCategory $taskCategory): JsonResponse
    {
        try {
            $this->authorize('delete', $taskCategory);

            $this->service->delete($taskCategory);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskCategory' => $taskCategory->id]);
        }
    }

    /**
     * POST /api/task-categories/reorder — resequence the rows (AC-049). Gated on
     * `task-categories.update` directly: no single Model instance exists for a bulk
     * reorder, so there is no Policy `update($user, $model)` to delegate to
     * — mirrors TaskStatusController::reorder.
     */
    public function reorder(ReorderStatusesRequest $request): JsonResponse
    {
        try {
            $this->authorize('task-categories.update');

            $reordered = $this->service->reorder($request->orderedIds());

            return $this->ok($reordered->map(static fn (TaskCategory $taskCategory): array => [
                'id' => $taskCategory->id,
                'sort_order' => $taskCategory->sort_order,
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
    private function buildPermissions(User $actor, ?TaskCategory $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('task-categories'), $actor, $model);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Tasks\ReorderSubtasksRequest;
use App\Http\Resources\TaskSubtaskResource;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskSubtaskReorderService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/tasks/{task}/subtasks/reorder (spec 0155, D-4/D-5, contract):
 * `{ ids: int[] }` -> `200 { data: subtasks[] }`, 403 without `update` on the
 * parent, 422 when `ids` is not an exact permutation of its direct children.
 * Invokable, single action: no other verb exists on this route.
 */
class TaskSubtaskReorderController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskSubtaskReorderService $service) {}

    public function __invoke(ReorderSubtasksRequest $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('update', $task);

            /** @var User $actor */
            $actor = $request->user();
            $subtasks = $this->service->reorder($task, $request->subtaskIds(), $actor);

            return $this->ok(TaskSubtaskResource::collection($subtasks));
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['task' => $task->id]);
        }
    }
}

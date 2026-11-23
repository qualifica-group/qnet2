<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tasks;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Exceptions\Tasks\TaskBulkIncompatibleException;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Tasks\CompleteTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskCascadeCompletionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/tasks/{task}/complete (spec 0116, data_contract). Invokable,
 * single action: no other verb exists on this route. On a Task with open
 * sub-tasks the write closes them too (spec 0418); a refused sub-task answers
 * with the bulk endpoint's own `incompatible_tasks` 422.
 */
class TaskCompleteController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskCascadeCompletionService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function __invoke(CompleteTaskRequest $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('complete', $task);

            /** @var User $actor */
            $actor = $request->user();
            /** @var array<string, mixed> $validated */
            $validated = $request->validated();
            $task = $this->service->complete($task, $validated, $actor);

            return $this->okWithPermissions(
                new TaskResource($task),
                $this->buildPermissions($actor, $task),
            );
        } catch (TaskBulkIncompatibleException $exception) {
            return $exception->toResponse();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['task' => $task->id]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?Task $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('tasks'), $actor, $model);
    }
}

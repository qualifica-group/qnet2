<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tasks;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Tasks\ApproveTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskCompletionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/tasks/{task}/approve (spec 0116, data_contract). Invokable,
 * single action: no other verb exists on this route.
 */
class TaskApproveController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskCompletionService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function __invoke(ApproveTaskRequest $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('validate', $task);

            /** @var User $actor */
            $actor = $request->user();
            $task = $this->service->approve($task, $actor);

            return $this->okWithPermissions(
                new TaskResource($task),
                $this->buildPermissions($actor, $task),
            );
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

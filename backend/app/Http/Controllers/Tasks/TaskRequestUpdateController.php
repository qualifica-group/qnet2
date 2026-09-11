<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tasks;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Tasks\RequestTaskUpdateRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskActionService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/tasks/{task}/request-update (spec 0118, D-10..D-14). Invokable,
 * single action: no other verb exists on this route. The seventh domain
 * action, modelled on its six siblings (TaskCompleteController et al.),
 * though the Task itself is never written (D-14).
 */
class TaskRequestUpdateController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskActionService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    public function __invoke(RequestTaskUpdateRequest $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('requestUpdate', $task);

            /** @var User $actor */
            $actor = $request->user();
            $task = $this->service->requestUpdate($task, $request->toData(), $actor);

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

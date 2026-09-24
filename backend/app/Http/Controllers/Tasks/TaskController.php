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
use Illuminate\Auth\Access\AuthorizationException;
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

    /** Spec 0155, D-7: the message shown alongside `errors.access_contacts`. */
    private const string ACCESS_DENIED_MESSAGE = 'You do not have the permissions needed to view this task. To request access, contact one of the people responsible for it and ask to be added as a watcher.';

    /**
     * GET /api/tasks/{task} — single Task (view row-action). 403 both without
     * `tasks.view` and for a Task outside the actor's visibility scope
     * (AC-060). A missing Task still 404s (route-model binding fails before
     * this method ever runs, spec 0155 D-7).
     *
     * The 403 is handled HERE rather than falling through to
     * handleControllerException() (spec 0155, D-7, REQUIREMENT CHANGED): it
     * carries `errors.access_contacts` (richiedente + creatore, deduplicated)
     * and is deliberately NOT an error-log / Teams-alert incident — an actor
     * without membership on a Task is an expected, everyday outcome, not a
     * backend fault. `AuthorizationException` is not `HttpExceptionInterface`,
     * so BaseApiController's own skip-on-HttpException carve-out cannot reach
     * it; this catch runs first instead of touching that shared class.
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
        } catch (AuthorizationException) {
            return $this->fail(
                __(self::ACCESS_DENIED_MESSAGE),
                HttpStatusEnum::FORBIDDEN->value,
                ['access_contacts' => $this->accessContacts($task)],
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
     * PUT/PATCH /api/tasks/{task} — update an existing Task. The actor is
     * handed to the Service because the notification map excludes whoever
     * performed the action from its own recipients (spec 0119 D-3).
     */
    public function update(UpdateTaskRequest $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('update', $task);

            /** @var User $actor */
            $actor = $request->user();
            $task = $this->service->update($task, $request->toData(), $actor);

            return $this->okWithPermissions(
                new TaskResource($task),
                $this->buildPermissions($actor, $task),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['task' => $task->id]);
        }
    }

    /**
     * DELETE /api/tasks/{task} — delete a Task, unless it has sub-tasks
     * (TaskService::delete(), D-8a: 409).
     */
    public function destroy(Request $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('delete', $task);

            /** @var User $actor */
            $actor = $request->user();
            $this->service->delete($task, $actor);

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

    /**
     * $task's requester and creator, deduplicated, skipping a null requester
     * (spec 0155, D-7) — who a denied actor may reach out to for access.
     *
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function accessContacts(Task $task): array
    {
        $task->loadMissing(['requester', 'creator']);

        $contacts = collect([$task->requester, $task->creator])->filter()->unique('id');

        return $contacts
            ->map(static fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email])
            ->values()
            ->all();
    }
}

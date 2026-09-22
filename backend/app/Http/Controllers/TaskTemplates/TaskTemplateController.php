<?php

declare(strict_types=1);

namespace App\Http\Controllers\TaskTemplates;

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ResourcePermissionsBuilder;
use App\Enums\HttpStatusEnum;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\TaskTemplates\StoreTaskTemplateRequest;
use App\Http\Requests\TaskTemplates\UpdateTaskTemplateRequest;
use App\Http\Resources\TaskTemplateResource;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\TaskTemplateService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * CRUD endpoints for the `task-templates` resource (spec 0124), backing the
 * backend-driven table row-actions (view/edit/delete) plus create.
 *
 * Thin controller: validation (FormRequest), server-side authorization
 * (TaskTemplatePolicy), Service call, response. No business logic, no
 * queries.
 *
 * show/store/update also attach the `permissions` metadata block (spec 0004)
 * via ResourcePermissionsBuilder, contextual to the returned model.
 *
 * @see TaskTemplateService
 */
class TaskTemplateController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(
        private readonly TaskTemplateService $service,
        private readonly AuthorizationRegistry $authorization,
        private readonly ResourcePermissionsBuilder $permissionsBuilder,
    ) {}

    /**
     * GET /api/task-templates/{taskTemplate} — single template (view
     * row-action), with its ordered items.
     */
    public function show(Request $request, TaskTemplate $taskTemplate): JsonResponse
    {
        try {
            $this->authorize('view', $taskTemplate);

            $taskTemplate->load(['stages', 'items.taskStatus', 'items.attachments']);

            return $this->okWithPermissions(
                new TaskTemplateResource($taskTemplate),
                $this->buildPermissions($request->user(), $taskTemplate),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskTemplate' => $taskTemplate->id]);
        }
    }

    /**
     * POST /api/task-templates — create a new template with its rows.
     */
    public function store(StoreTaskTemplateRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', TaskTemplate::class);

            $taskTemplate = $this->service->create($request->toData(), $request->user());

            return $this->okWithPermissions(
                new TaskTemplateResource($taskTemplate),
                $this->buildPermissions($request->user(), $taskTemplate),
                'Created',
                HttpStatusEnum::CREATED,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * PUT/PATCH /api/task-templates/{taskTemplate} — update an existing
     * template; `items`, when submitted, is a full sync (AC-004).
     */
    public function update(UpdateTaskTemplateRequest $request, TaskTemplate $taskTemplate): JsonResponse
    {
        try {
            $this->authorize('update', $taskTemplate);

            $taskTemplate = $this->service->update($taskTemplate, $request->toData(), $request->user());

            return $this->okWithPermissions(
                new TaskTemplateResource($taskTemplate),
                $this->buildPermissions($request->user(), $taskTemplate),
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskTemplate' => $taskTemplate->id]);
        }
    }

    /**
     * DELETE /api/task-templates/{taskTemplate} — delete a template (guarded
     * by TaskTemplateService::delete(), spec 0124 D-5).
     */
    public function destroy(TaskTemplate $taskTemplate): JsonResponse
    {
        try {
            $this->authorize('delete', $taskTemplate);

            $this->service->delete($taskTemplate);

            return $this->noContent();
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['taskTemplate' => $taskTemplate->id]);
        }
    }

    /**
     * The `permissions` block for $model, contextual to $actor (spec 0004).
     *
     * @return array<string, mixed>
     */
    private function buildPermissions(User $actor, ?TaskTemplate $model): array
    {
        return $this->permissionsBuilder->build($this->authorization->resolve('task-templates'), $actor, $model);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tasks;

use App\DataObjects\Tasks\BulkTaskData;
use App\Exceptions\Tasks\TaskBulkIncompatibleException;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Tasks\BulkTaskRequest;
use App\Models\User;
use App\Services\Tasks\TaskBulkService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * POST /api/tasks/bulk (spec 0156, D-6). Invokable, single action.
 *
 * Authorizes only the RESOURCE-level base permission of the chosen action
 * here (contract: "403 senza il permesso base dell'azione" — assign/
 * priority/start_date/end_date -> tasks.update, complete/uncomplete ->
 * tasks.complete, block/unblock -> tasks.block, delete -> tasks.delete): the
 * PER-TASK ability (the record-role matrix) is re-asserted per row by
 * TaskBulkActionExecutor inside TaskBulkService, which turns a per-row 403
 * into an `incompatible_tasks` entry instead of aborting the request.
 *
 * TaskBulkIncompatibleException is caught AHEAD of the generic exception
 * handler because the frozen contract puts `incompatible_tasks` at the
 * envelope's ROOT — a shape `BaseApiController::fail()`'s `$data` parameter
 * cannot express (that one nests under `data`).
 */
class TaskBulkController extends BaseApiController
{
    /**
     * The RESOURCE-level permission each action requires, independent of
     * the record-role matrix TaskBulkActionExecutor re-asserts per row.
     *
     * @var array<string, string>
     */
    private const array BASE_PERMISSIONS = [
        'assign' => 'tasks.update',
        'priority' => 'tasks.update',
        'start_date' => 'tasks.update',
        'end_date' => 'tasks.update',
        'complete' => 'tasks.complete',
        'uncomplete' => 'tasks.complete',
        'block' => 'tasks.block',
        'unblock' => 'tasks.block',
        'delete' => 'tasks.delete',
    ];

    public function __construct(private readonly TaskBulkService $service) {}

    public function __invoke(BulkTaskRequest $request): JsonResponse
    {
        try {
            $data = $request->toData();

            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeBasePermission($actor, $data);

            $affected = $this->service->execute($data, $actor);

            return $this->ok(['affected' => $affected], 'Bulk action completed');
        } catch (TaskBulkIncompatibleException $exception) {
            return response()->json([
                'success' => false,
                'message' => __($exception->getMessage()),
                'errors' => ['task_ids' => [__($exception->getMessage())]],
                'incompatible_tasks' => $exception->incompatibleTasks,
            ], 422);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeBasePermission(User $actor, BulkTaskData $data): void
    {
        $permission = self::BASE_PERMISSIONS[$data->action] ?? null;

        if ($permission === null || ! $actor->can($permission)) {
            throw new AuthorizationException;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskOpenSubtree;
use App\Services\Tasks\TaskTimeEntryRequirement;
use App\Services\Tasks\TaskVisibilityScope;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * GET /api/tasks/{task}/open-subtasks (spec 0418): the open sub-tasks the
 * Task's Completa closes with it, for the pop-up's notice and "Ripartizione"
 * rows. Only the ones the actor may see are listed (D-6): an invisible one
 * still makes the Completa incompatible, as it always blocked it.
 */
class TaskOpenSubtasksController extends BaseApiController
{
    use AuthorizesRequests;

    public function __construct(private readonly TaskOpenSubtree $openSubtree) {}

    public function __invoke(Request $request, Task $task): JsonResponse
    {
        try {
            $this->authorize('complete', $task);

            /** @var User $actor */
            $actor = $request->user();
            $nodes = $this->openSubtree->collect($task);
            $visibleIds = TaskVisibilityScope::scopeToActor(Task::query(), $actor)
                ->whereIn('id', array_map(fn (array $node): int => $node['task']->id, $nodes))
                ->pluck('id')
                ->flip();

            $rows = array_values(array_map(fn (array $node): array => [
                'id' => $node['task']->id,
                'title' => $node['task']->title,
                'depth' => $node['depth'],
                'requires_time_entry' => TaskTimeEntryRequirement::isRequired($node['task']),
                'requires_closure_feedback' => (bool) $node['task']->requires_closure_feedback,
            ], array_filter($nodes, fn (array $node): bool => $visibleIds->has($node['task']->id))));

            return $this->ok($rows);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['task' => $task->id]);
        }
    }
}

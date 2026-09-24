<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\Tasks\BulkTaskData;
use App\Enums\TaskStatusGroup;
use App\Exceptions\Tasks\TaskBulkIncompatibleException;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * `POST /api/tasks/bulk` (spec 0156, D-6): unlike the task board's own bulk
 * (best-effort, one commessa), this one is ALL-OR-NOTHING over any set of
 * visible Tasks — "si verificano tutte le righe prima ... altrimenti una
 * transazione". Rather than a separate dry-run pass, every task's write
 * runs for REAL inside its own SAVEPOINT (a `DB::transaction()` nested
 * inside the outer one Laravel turns into a savepoint automatically): a
 * per-task failure rolls back only that savepoint and is recorded as
 * `incompatible`, the loop continues. Once every task has been attempted, an
 * incompatible one at all throws (rolling back the WHOLE outer transaction —
 * "nulla cambia"); none does, the outer transaction commits and every write
 * already applied stands. This is observably identical to a separate
 * pre-check pass, without a second implementation of "would this succeed"
 * ever able to drift from the real write.
 *
 * Each task's write goes through the SAME App\Services\Tasks\
 * TaskBulkActionExecutor the task board's own bulk endpoint uses (D-9: the
 * per-task ability is re-asserted there, a 403 becoming an `incompatible`
 * entry here rather than aborting the batch) — never a shortcut.
 */
final class TaskBulkService
{
    public function __construct(private readonly TaskBulkActionExecutor $executor) {}

    public function execute(BulkTaskData $data, User $actor): int
    {
        return DB::transaction(function () use ($data, $actor): int {
            $tasks = $this->resolveVisibleTasks($data->taskIds, $actor);
            $incompatible = [];

            foreach ($data->taskIds as $id) {
                $task = $tasks->get($id);

                if ($task === null) {
                    $incompatible[] = ['id' => $id, 'reason' => __('This task is not available.')];

                    continue;
                }

                try {
                    DB::transaction(fn () => $this->applyAction($data, $task, $actor));
                } catch (AuthorizationException|ValidationException|HttpExceptionInterface $exception) {
                    $incompatible[] = ['id' => $id, 'reason' => $this->messageFor($exception)];
                }
            }

            if ($incompatible !== []) {
                throw new TaskBulkIncompatibleException($incompatible);
            }

            return count($data->taskIds);
        });
    }

    private function applyAction(BulkTaskData $data, Task $task, User $actor): void
    {
        match ($data->action) {
            'assign' => $this->executor->assign($task, $data->assigneeIds ?? [], $actor),
            // for_all_assignees is ALWAYS true here (contract): unlike the
            // task board's own bulk complete, every assignee gets the SAME
            // segnatempo logged. validation_status_id falls back to the
            // first active in_validation status by sort_order when the Task
            // requires validation and the caller submitted none.
            'complete' => $this->executor->complete(
                $task,
                $data->timeEntry ?? [],
                $data->closureFeedback,
                $data->closureFeedbackSubmitted,
                $data->validationStatusId ?? $this->defaultValidationStatusId($actor, $task),
                true,
                $actor,
            ),
            'uncomplete' => $this->executor->uncomplete($task, $actor),
            'block' => $this->executor->block($task, $actor),
            'unblock' => $this->executor->unblock($task, $actor),
            'priority' => $this->executor->priority($task, (int) $data->taskPriorityId, $actor),
            'start_date' => $this->executor->dates($task, $data->date, true, null, false, $actor),
            'end_date' => $this->executor->dates($task, null, false, $data->date, true, $actor),
            'delete' => $this->executor->delete($task, $actor),
        };
    }

    /**
     * Contract: "assente = primo stato attivo del gruppo in_validation per
     * sort_order" — resolved only when $task's completion actually TAKES the
     * validation percorso (TaskAbilityResolver::completionRequiresValidation()),
     * so a Task that closes directly never even queries for one.
     */
    private function defaultValidationStatusId(User $actor, Task $task): ?int
    {
        if (! TaskAbilityResolver::completionRequiresValidation($actor, $task)) {
            return null;
        }

        return TaskStatus::query()
            ->where('is_active', true)
            ->where('group', TaskStatusGroup::InValidation->value)
            ->orderBy('sort_order')
            ->value('id');
    }

    /**
     * Contract: "un id non visibile all'utente e' incompatibile" — resolved
     * through the SAME TaskVisibilityScope the grid itself is scoped by, so
     * an id outside the actor's visibility is indistinguishable from one
     * that does not exist at all.
     *
     * @param  array<int, int>  $taskIds
     * @return Collection<int, Task>
     */
    private function resolveVisibleTasks(array $taskIds, User $actor): Collection
    {
        return TaskVisibilityScope::scopeToActor(Task::query(), $actor)
            ->whereIn('id', $taskIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * Mirrors App\Services\WorkOrders\TaskBulkActionService::messageFor().
     */
    private function messageFor(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            /** @var string $message */
            $message = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();

            return __($message);
        }

        return __($exception->getMessage());
    }
}

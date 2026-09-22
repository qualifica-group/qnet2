<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\DataObjects\Tasks\CompleteTaskData;
use App\DataObjects\Tasks\UpdateTaskData;
use App\DataObjects\WorkOrders\BulkTaskBoardData;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Tasks\TaskActionService;
use App\Services\Tasks\TaskCompletionService;
use App\Services\TaskService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The six bulk actions of the task board (spec 0146, D-7): assign, complete,
 * uncomplete, block, priority, dates. Every task runs through the SAME
 * domain Service a single-task endpoint would use — `TaskService::update()`
 * for assign/priority/dates, `TaskCompletionService` for complete/uncomplete,
 * `TaskActionService::block()` — re-asserting its own ability (`Gate::
 * forUser($actor)->authorize()`, D-9) INSIDE its own transaction, so a
 * failure on one task never touches another (AC-017/AC-018: "nessuna
 * modifica parziale sul task che fallisce").
 *
 * A task that requires validation still fails `complete()` with an explicit
 * `validation_status_id` `ValidationException` (`TaskCompletionService`'s own
 * D-3 guard): this endpoint never submits that field, so the validation
 * percorso is deliberately unreachable here, exactly as the data_contract
 * requires ("la validazione resta un'azione singola").
 *
 * The commessa-closed veto (D-9) and the "every id is a root of THIS
 * commessa" check (422) are BLANKET, ahead of the per-task loop: neither is
 * a per-task outcome the `results` array could express.
 */
final class TaskBulkActionService
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskCompletionService $completionService,
        private readonly TaskActionService $actionService,
    ) {}

    /**
     * @return array{results: array<int, array{task_id: int, ok: bool, message: string|null}>, succeeded: int, failed: int}
     */
    public function execute(WorkOrder $workOrder, BulkTaskBoardData $data, User $actor): array
    {
        WorkOrderClosedGuard::assertOpen($workOrder);
        $this->assertEveryTaskIsARootOf($workOrder, $data->taskIds);

        $results = [];
        $succeeded = 0;
        $failed = 0;

        foreach ($data->taskIds as $taskId) {
            /** @var Task $task */
            $task = Task::query()->findOrFail($taskId);

            try {
                DB::transaction(fn () => $this->applyAction($task, $data, $actor));
                $results[] = ['task_id' => $taskId, 'ok' => true, 'message' => null];
                $succeeded++;
            } catch (AuthorizationException|ValidationException|HttpExceptionInterface $exception) {
                $results[] = ['task_id' => $taskId, 'ok' => false, 'message' => $this->messageFor($exception)];
                $failed++;
            }
        }

        return ['results' => $results, 'succeeded' => $succeeded, 'failed' => $failed];
    }

    private function applyAction(Task $task, BulkTaskBoardData $data, User $actor): void
    {
        match ($data->action) {
            'assign' => $this->assign($task, $data, $actor),
            'complete' => $this->complete($task, $data, $actor),
            'uncomplete' => $this->uncomplete($task, $actor),
            'block' => $this->block($task, $actor),
            'priority' => $this->priority($task, $data, $actor),
            'dates' => $this->dates($task, $data, $actor),
        };
    }

    /** "canUpdate" (D-7): the same ability a single-task PATCH is gated on. */
    private function assign(Task $task, BulkTaskBoardData $data, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $task);

        $this->taskService->update($task, UpdateTaskData::fromValidated([
            'assignee_ids' => $data->assigneeIds,
        ]), $actor);
    }

    /** "canComplete" (D-7): TaskPolicy::complete covers both completion directions. */
    private function complete(Task $task, BulkTaskBoardData $data, User $actor): void
    {
        Gate::forUser($actor)->authorize('complete', $task);

        $payload = ['time_entry' => $data->timeEntry];

        if ($data->closureFeedbackSubmitted) {
            $payload['closure_feedback'] = $data->closureFeedback;
        }

        $this->completionService->complete($task, CompleteTaskData::fromValidated($payload, $task->id), $actor);
    }

    private function uncomplete(Task $task, User $actor): void
    {
        Gate::forUser($actor)->authorize('complete', $task);
        $this->completionService->uncomplete($task, $actor);
    }

    /** "canBlock" (D-7). */
    private function block(Task $task, User $actor): void
    {
        Gate::forUser($actor)->authorize('block', $task);
        $this->actionService->block($task, $actor);
    }

    private function priority(Task $task, BulkTaskBoardData $data, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $task);

        $this->taskService->update($task, UpdateTaskData::fromValidated([
            'task_priority_id' => $data->taskPriorityId,
        ]), $actor);
    }

    /**
     * Only the submitted date key(s) are touched (data_contract: "almeno una
     * delle due"), the same sparse-PATCH shape `UpdateTaskData` already
     * gives a single-task update.
     */
    private function dates(Task $task, BulkTaskBoardData $data, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $task);

        $payload = [];

        if ($data->startDateSubmitted) {
            $payload['start_date'] = $data->startDate;
        }

        if ($data->endDateSubmitted) {
            $payload['end_date'] = $data->endDate;
        }

        $this->taskService->update($task, UpdateTaskData::fromValidated($payload), $actor);
    }

    /**
     * @param  array<int, int>  $taskIds
     */
    private function assertEveryTaskIsARootOf(WorkOrder $workOrder, array $taskIds): void
    {
        $rootCount = Task::query()
            ->whereIn('id', $taskIds)
            ->where('work_order_id', $workOrder->id)
            ->whereNull('parent_task_id')
            ->count();

        abort_if($rootCount !== count($taskIds), 422, 'One or more selected tasks are not roots of this commessa.');
    }

    /**
     * The i18n motivo of a single task's failure (data_contract: "message
     * e' il motivo i18n del fallimento del singolo task"): a ValidationException
     * carries its OWN field errors, so its first message wins over the
     * framework's generic "The given data was invalid." — everything else
     * (403/409/422 aborts) already has its own catalogued sentence.
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

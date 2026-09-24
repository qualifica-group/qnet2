<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\DataObjects\WorkOrders\BulkTaskBoardData;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Tasks\TaskBulkActionExecutor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * The six bulk actions of the task board (spec 0146, D-7): assign, complete,
 * uncomplete, block, priority, dates. Every task runs through the SAME
 * domain Service a single-task endpoint would use, via the SHARED
 * per-task write App\Services\Tasks\TaskBulkActionExecutor also gives
 * App\Services\Tasks\TaskBulkService (spec 0156's generic `/api/tasks/bulk`)
 * — re-asserting its own ability INSIDE its own transaction, so a failure on
 * one task never touches another (AC-017/AC-018: "nessuna modifica parziale
 * sul task che fallisce").
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
    public function __construct(private readonly TaskBulkActionExecutor $executor) {}

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
            'assign' => $this->executor->assign($task, $data->assigneeIds ?? [], $actor),
            // Never `for_all_assignees`/`validation_status_id`: the board
            // keeps its own pre-0156 shape (docblock above).
            'complete' => $this->executor->complete($task, $data->timeEntry ?? [], $data->closureFeedback, $data->closureFeedbackSubmitted, null, false, $actor),
            'uncomplete' => $this->executor->uncomplete($task, $actor),
            'block' => $this->executor->block($task, $actor),
            'priority' => $this->executor->priority($task, (int) $data->taskPriorityId, $actor),
            'dates' => $this->executor->dates($task, $data->startDate, $data->startDateSubmitted, $data->endDate, $data->endDateSubmitted, $actor),
        };
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

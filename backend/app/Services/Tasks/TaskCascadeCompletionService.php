<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\Tasks\CompleteTaskData;
use App\Exceptions\Tasks\TaskBulkIncompatibleException;
use App\Http\Requests\TimeEntries\BulkTimeEntrySharesRules;
use App\Models\Task;
use App\Models\User;
use App\Services\Notifications\TaskNotifier;
use App\Services\TaskService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * `POST /api/tasks/{task}/complete` (spec 0418). A Task without open
 * sub-tasks goes straight to TaskCompletionService::complete(), unchanged.
 * A Task WITH open sub-tasks closes them with it, all-or-nothing (D-1/D-4):
 * the open sub-tree (TaskOpenSubtree) closes deepest first, each sub-task
 * through the SAME TaskCompletionService::apply() and its own savepoint, so a
 * refused one becomes an `incompatible_tasks` entry; any entry at all rolls
 * the whole write back. The Task itself closes last, once nothing below it is
 * open any more, and its own errors surface as plain field errors.
 *
 * The submitted `time_entry` is then the TOTAL, split by `time_entry_shares`
 * (D-2) with the bulk Completa's own splitter (spec 0338): one share per task
 * of the operation, consecutive slots in the order of the shares.
 */
final class TaskCascadeCompletionService
{
    public function __construct(
        private readonly TaskCompletionService $completion,
        private readonly TaskOpenSubtree $openSubtree,
        private readonly TaskBulkTimeEntrySplitter $splitter,
        private readonly TaskService $taskService,
        private readonly TaskNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $validated  CompleteTaskRequest's validated payload
     */
    public function complete(Task $task, array $validated, User $actor): Task
    {
        $subtasks = array_column($this->openSubtree->collect($task), 'task');

        if ($subtasks === []) {
            return $this->completion->complete($task, CompleteTaskData::fromValidated($validated, $task->id), $actor);
        }

        // Spec 0428: the parent and its sub-tasks reach each recipient as one summary.
        $this->notifier->summarizingClosures(fn () => DB::transaction(function () use ($task, $subtasks, $validated, $actor): void {
            // Step 1: one time entry per task, out of the total
            $timeEntries = $this->splitTimeEntry($task, $subtasks, $validated);

            // Step 2: the open sub-tasks, deepest first
            $this->completeSubtasks(array_reverse($subtasks), $validated, $timeEntries, $actor);

            // Step 3: the Task itself, with its own share and validation status
            $this->completion->apply($task, CompleteTaskData::fromValidated(
                $this->payloadFor($validated, $timeEntries[$task->id] ?? null, true),
                $task->id,
            ), $actor);
        }));

        return $this->taskService->loadDetail($task->fresh());
    }

    /**
     * @param  list<Task>  $subtasks
     * @param  array<string, mixed>  $validated
     * @return array<int, array<string, mixed>> keyed by task id, empty without a time entry
     */
    private function splitTimeEntry(Task $task, array $subtasks, array $validated): array
    {
        if (! isset($validated['time_entry'])) {
            return [];
        }

        $shares = BulkTimeEntrySharesRules::normalize((array) ($validated[BulkTimeEntrySharesRules::FIELD] ?? []));
        $expected = [$task->id, ...array_map(fn (Task $subtask): int => $subtask->id, $subtasks)];
        $submitted = array_column($shares, 'task_id');

        sort($expected);
        sort($submitted);

        if ($expected !== $submitted) {
            throw ValidationException::withMessages([
                BulkTimeEntrySharesRules::FIELD => [__('The shares must cover this task and each of its open sub-tasks.')],
            ]);
        }

        return $this->splitter->split((array) $validated['time_entry'], $shares);
    }

    /**
     * @param  list<Task>  $subtasks  deepest first
     * @param  array<string, mixed>  $validated
     * @param  array<int, array<string, mixed>>  $timeEntries
     */
    private function completeSubtasks(array $subtasks, array $validated, array $timeEntries, User $actor): void
    {
        $incompatible = [];

        foreach ($subtasks as $subtask) {
            try {
                DB::transaction(fn () => $this->completeSubtask($subtask, $this->payloadFor($validated, $timeEntries[$subtask->id] ?? null, false), $actor));
            } catch (AuthorizationException|ValidationException|HttpExceptionInterface $exception) {
                $incompatible[] = ['id' => $subtask->id, 'reason' => TaskBulkIncompatibleException::reasonFor($exception)];
            }
        }

        if ($incompatible !== []) {
            throw new TaskBulkIncompatibleException($incompatible);
        }
    }

    /**
     * Same per-task authorization as a single Completa (TaskPolicy::complete,
     * visibility included). A sub-task whose completion would only send it to
     * validation stays open, and an open sub-task keeps the parent open: it is
     * refused here with its own reason instead (D-4).
     *
     * @param  array<string, mixed>  $payload
     */
    private function completeSubtask(Task $subtask, array $payload, User $actor): void
    {
        Gate::forUser($actor)->authorize('complete', $subtask);

        if (TaskAbilityResolver::completionRequiresValidation($actor, $subtask)) {
            abort(422, 'This sub-task requires validation: complete it on its own first.');
        }

        $this->completion->apply($subtask, CompleteTaskData::fromValidated($payload, $subtask->id), $actor);
    }

    /**
     * D-3: feedback and "for all assignees" apply to every task, the
     * validation status to the Task alone, the time entry is the task's share.
     *
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>|null  $timeEntry
     * @return array<string, mixed>
     */
    private function payloadFor(array $validated, ?array $timeEntry, bool $isRoot): array
    {
        $payload = $validated;
        unset($payload['time_entry'], $payload[BulkTimeEntrySharesRules::FIELD]);

        if (! $isRoot) {
            unset($payload['validation_status_id']);
        }

        if ($timeEntry !== null) {
            $payload['time_entry'] = $timeEntry;
        }

        return $payload;
    }
}

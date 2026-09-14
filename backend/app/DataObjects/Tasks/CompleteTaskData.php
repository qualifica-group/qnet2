<?php

declare(strict_types=1);

namespace App\DataObjects\Tasks;

use App\DataObjects\TimeEntries\TimeEntryData;

/**
 * Validated payload for POST /api/tasks/{task}/complete (spec 0116,
 * data_contract; percorso di completamento riscritto da spec 0121, D-2/D-3;
 * `time_entry` added by spec 0123, D-1). `validation_status_id` no longer
 * decides WHICH percorso the action takes —
 * `App\Services\Tasks\TaskAbilityResolver::completionRequiresValidation()`
 * does, off `requires_validation` and the actor's mandate — it only carries
 * WHICH `in_validation` status the caller chose, and only when that percorso
 * applies (`TaskCompletionService::complete()` enforces both directions of the
 * requirement with a 422 on this field). `closure_feedback` applies to
 * either percorso and follows the nullable-column convention of
 * UpdateTaskData: the flag, not the value, decides whether the column is
 * written.
 *
 * `timeEntry` is mandatory on BOTH percorsi (D-1) and built via
 * `TimeEntryData::forTask()`, the same derivation the task-scoped segnatempo
 * POST uses — title and record links come from the Task, never the payload.
 */
final readonly class CompleteTaskData
{
    public function __construct(
        public TimeEntryData $timeEntry,
        public ?string $closureFeedback = null,
        public bool $closureFeedbackSubmitted = false,
        public ?int $validationStatusId = null,
        public bool $validationStatusIdSubmitted = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data, int $taskId): self
    {
        return new self(
            timeEntry: TimeEntryData::forTask((array) $data['time_entry'], $taskId),
            closureFeedback: array_key_exists('closure_feedback', $data) && $data['closure_feedback'] !== null
                ? (string) $data['closure_feedback']
                : null,
            closureFeedbackSubmitted: array_key_exists('closure_feedback', $data),
            validationStatusId: array_key_exists('validation_status_id', $data) && $data['validation_status_id'] !== null
                ? (int) $data['validation_status_id']
                : null,
            validationStatusIdSubmitted: array_key_exists('validation_status_id', $data) && $data['validation_status_id'] !== null,
        );
    }
}

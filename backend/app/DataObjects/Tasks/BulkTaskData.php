<?php

declare(strict_types=1);

namespace App\DataObjects\Tasks;

/**
 * Validated payload for POST /api/tasks/bulk (spec 0156, D-6/contract).
 * `App\Http\Requests\Tasks\BulkTaskRequest` enforces the per-action shape
 * (only the fields the chosen `action` needs are ever non-null); this DTO
 * just carries the result, mirroring `App\DataObjects\WorkOrders\
 * BulkTaskBoardData`.
 *
 * `timeEntry` stays a RAW array rather than its own DTO, same reason as its
 * board twin: it is handed straight to `TimeEntryData::forTask()` one task
 * at a time — wrapping it here would only be a DTO around a DTO.
 */
final readonly class BulkTaskData
{
    /**
     * @param  array<int, int>  $taskIds
     * @param  array<int, int>|null  $assigneeIds
     * @param  array<string, mixed>|null  $timeEntry
     */
    public function __construct(
        public string $action,
        public array $taskIds,
        public ?array $assigneeIds = null,
        public ?int $taskPriorityId = null,
        public ?string $date = null,
        public ?array $timeEntry = null,
        public ?string $closureFeedback = null,
        public bool $closureFeedbackSubmitted = false,
        public ?int $validationStatusId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            action: (string) $data['action'],
            taskIds: array_map(intval(...), (array) $data['task_ids']),
            assigneeIds: isset($data['assignee_ids']) ? array_map(intval(...), (array) $data['assignee_ids']) : null,
            taskPriorityId: isset($data['task_priority_id']) ? (int) $data['task_priority_id'] : null,
            date: isset($data['date']) ? (string) $data['date'] : null,
            timeEntry: isset($data['time_entry']) ? (array) $data['time_entry'] : null,
            closureFeedback: array_key_exists('closure_feedback', $data) && $data['closure_feedback'] !== null
                ? (string) $data['closure_feedback']
                : null,
            closureFeedbackSubmitted: array_key_exists('closure_feedback', $data),
            validationStatusId: isset($data['validation_status_id']) ? (int) $data['validation_status_id'] : null,
        );
    }
}

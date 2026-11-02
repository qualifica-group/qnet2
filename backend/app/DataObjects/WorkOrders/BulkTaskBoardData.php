<?php

declare(strict_types=1);

namespace App\DataObjects\WorkOrders;

use App\Http\Requests\TimeEntries\BulkTimeEntrySharesRules;

/**
 * Validated payload for POST /api/work-orders/{workOrder}/task-board/bulk
 * (spec 0146, D-7/data_contract). Only the fields the chosen `action` needs
 * are ever non-null — `App\Http\Requests\TaskBoard\BulkTaskBoardRequest`
 * enforces that per-action shape, this DTO just carries the result.
 *
 * `timeEntry`/`block` stay RAW arrays rather than their own DTOs: they are
 * handed straight to `TimeEntryData::forTask()`/`BlockTaskRequest`'s sibling
 * shape inside `App\Services\WorkOrders\TaskBulkActionService`, one task at a
 * time — wrapping them here would only be a DTO around a DTO.
 */
final readonly class BulkTaskBoardData
{
    /**
     * @param  array<int, int>  $taskIds
     * @param  array<int, int>|null  $assigneeIds
     * @param  array<string, mixed>|null  $timeEntry
     * @param  array<int, array{task_id: int, minutes: int}>|null  $timeEntryShares
     */
    public function __construct(
        public string $action,
        public array $taskIds,
        public ?array $assigneeIds = null,
        public ?string $closureFeedback = null,
        public bool $closureFeedbackSubmitted = false,
        public bool $forAllAssignees = false,
        public ?array $timeEntry = null,
        public ?int $taskPriorityId = null,
        public ?string $startDate = null,
        public bool $startDateSubmitted = false,
        public ?string $endDate = null,
        public bool $endDateSubmitted = false,
        public ?array $timeEntryShares = null,
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
            closureFeedback: array_key_exists('closure_feedback', $data) && $data['closure_feedback'] !== null
                ? (string) $data['closure_feedback']
                : null,
            closureFeedbackSubmitted: array_key_exists('closure_feedback', $data),
            forAllAssignees: (bool) ($data['for_all_assignees'] ?? false),
            timeEntry: isset($data['time_entry']) ? (array) $data['time_entry'] : null,
            taskPriorityId: isset($data['task_priority_id']) ? (int) $data['task_priority_id'] : null,
            startDate: $data['start_date'] ?? null,
            startDateSubmitted: array_key_exists('start_date', $data),
            endDate: $data['end_date'] ?? null,
            endDateSubmitted: array_key_exists('end_date', $data),
            timeEntryShares: isset($data['time_entry_shares']) ? BulkTimeEntrySharesRules::normalize((array) $data['time_entry_shares']) : null,
        );
    }
}

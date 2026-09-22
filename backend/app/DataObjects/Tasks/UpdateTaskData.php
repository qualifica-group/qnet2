<?php

declare(strict_types=1);

namespace App\DataObjects\Tasks;

/**
 * Validated payload for a partial (PATCH) Task update
 * (PUT/PATCH /api/tasks/{task}, spec 0101).
 *
 * Every nullable column is a legitimately nullable VALUE, so a plain null
 * property cannot distinguish "not submitted" (leave as is) from "submitted
 * as null" (clear it): the `*Submitted` flags carry that distinction, the
 * same convention as UpdateTaskTypeData/UpdateWorkOrderData. `title`,
 * `taskStatusId` and `requiresClosureFeedback` are
 * `sometimes|required`/`sometimes|boolean` at the FormRequest layer, so a
 * non-null value already means "submitted" and they need no flag of their
 * own.
 *
 * `requiresValidation` (spec 0121, D-1) follows the same
 * `sometimes|required|boolean` shape as `requiresClosureFeedback`: a
 * non-null value already means "submitted".
 *
 * `assigneeIds`/`watcherIds` follow the collection convention instead: null
 * means "not submitted, leave the pivot untouched", an array — INCLUDING the
 * empty one — is an authoritative full-replace sync (AC-012).
 *
 * `recurrence` (spec 0120, data_contract) needs a THIRD state a plain
 * nullable property cannot express on its own: absent (leave the series as
 * it is), null (cancel it) and an object (create or replace it) are three
 * different instructions, not two — so it follows the `recurrenceSubmitted`
 * flag convention instead, `hasRecurrence()` mirroring `hasAssigneeIds()`.
 *
 * `creatorId` is GONE (D-10), `completionPercentage` never existed (D-6) and
 * `isBlocked` is GONE too (spec 0116 D-6): all three are `prohibited` at the
 * FormRequest layer and never reach this DTO — `is_blocked` is written only
 * by the block/unblock domain actions, never by a PATCH.
 *
 * submittedAttributes() is driven by the two static maps below rather than by
 * twenty-two near-identical `if` blocks: the sparse-PATCH contract is a
 * TABLE, and writing it as one keeps it readable and impossible to get
 * half-right.
 */
final readonly class UpdateTaskData
{
    /**
     * Columns whose FormRequest rule forbids an explicit null, keyed by
     * column name -> property name: a non-null property means "submitted".
     *
     * @var array<string, string>
     */
    private const array REQUIRED_WHEN_SUBMITTED = [
        'title' => 'title',
        'task_status_id' => 'taskStatusId',
        'requires_closure_feedback' => 'requiresClosureFeedback',
        'requires_validation' => 'requiresValidation',
    ];

    /**
     * Nullable columns, keyed by column name -> [value property, flag
     * property]: only the flag decides whether the column is written.
     * `description` is DELIBERATELY absent (spec 0128, D-2/D-3/D-4): the raw
     * HTML is never mass-assigned — TaskService::update() hands it to
     * TaskDescriptionWriter, which sanitizes it, turns its inline images into
     * the Task's own attachments and sets the column directly, gated on
     * `descriptionSubmitted` below the same way every other flag here is.
     *
     * @var array<string, array{string, string}>
     */
    private const array NULLABLE_COLUMNS = [
        'registry_id' => ['registryId', 'registryIdSubmitted'],
        'referent_id' => ['referentId', 'referentIdSubmitted'],
        'parent_task_id' => ['parentTaskId', 'parentTaskIdSubmitted'],
        'task_type_id' => ['taskTypeId', 'taskTypeIdSubmitted'],
        'task_priority_id' => ['taskPriorityId', 'taskPriorityIdSubmitted'],
        'task_importance_id' => ['taskImportanceId', 'taskImportanceIdSubmitted'],
        'task_category_id' => ['taskCategoryId', 'taskCategoryIdSubmitted'],
        'opportunity_id' => ['opportunityId', 'opportunityIdSubmitted'],
        'work_order_id' => ['workOrderId', 'workOrderIdSubmitted'],
        // Spec 0146, D-3/AC-015/AC-016: submitted vs. merely-inherited is
        // exactly what App\Services\Tasks\TaskStageGuard needs to tell an
        // explicit client mistake (422/409) apart from a value AC-016
        // detaches silently.
        'work_order_stage_id' => ['workOrderStageId', 'workOrderStageIdSubmitted'],
        'requester_id' => ['requesterId', 'requesterIdSubmitted'],
        'start_date' => ['startDate', 'startDateSubmitted'],
        'end_date' => ['endDate', 'endDateSubmitted'],
        'start_time' => ['startTime', 'startTimeSubmitted'],
        'end_time' => ['endTime', 'endTimeSubmitted'],
        'estimated_minutes' => ['estimatedMinutes', 'estimatedMinutesSubmitted'],
        'closure_feedback' => ['closureFeedback', 'closureFeedbackSubmitted'],
    ];

    /**
     * @param  array<int, int>|null  $assigneeIds
     * @param  array<int, int>|null  $watcherIds
     */
    public function __construct(
        public ?string $title = null,
        public ?int $taskStatusId = null,
        public ?bool $requiresClosureFeedback = null,
        public ?bool $requiresValidation = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?int $registryId = null,
        public bool $registryIdSubmitted = false,
        public ?int $referentId = null,
        public bool $referentIdSubmitted = false,
        public ?int $parentTaskId = null,
        public bool $parentTaskIdSubmitted = false,
        public ?int $taskTypeId = null,
        public bool $taskTypeIdSubmitted = false,
        public ?int $taskPriorityId = null,
        public bool $taskPriorityIdSubmitted = false,
        public ?int $taskImportanceId = null,
        public bool $taskImportanceIdSubmitted = false,
        public ?int $taskCategoryId = null,
        public bool $taskCategoryIdSubmitted = false,
        public ?int $opportunityId = null,
        public bool $opportunityIdSubmitted = false,
        public ?int $workOrderId = null,
        public bool $workOrderIdSubmitted = false,
        public ?int $workOrderStageId = null,
        public bool $workOrderStageIdSubmitted = false,
        public ?int $requesterId = null,
        public bool $requesterIdSubmitted = false,
        public ?string $startDate = null,
        public bool $startDateSubmitted = false,
        public ?string $endDate = null,
        public bool $endDateSubmitted = false,
        public ?string $startTime = null,
        public bool $startTimeSubmitted = false,
        public ?string $endTime = null,
        public bool $endTimeSubmitted = false,
        public ?int $estimatedMinutes = null,
        public bool $estimatedMinutesSubmitted = false,
        public ?string $closureFeedback = null,
        public bool $closureFeedbackSubmitted = false,
        public ?array $assigneeIds = null,
        public ?array $watcherIds = null,
        public ?TaskRecurrenceData $recurrence = null,
        public bool $recurrenceSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateTaskRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            title: isset($data['title']) ? (string) $data['title'] : null,
            taskStatusId: isset($data['task_status_id']) ? (int) $data['task_status_id'] : null,
            requiresClosureFeedback: isset($data['requires_closure_feedback']) ? (bool) $data['requires_closure_feedback'] : null,
            requiresValidation: isset($data['requires_validation']) ? (bool) $data['requires_validation'] : null,
            description: self::nullableString($data, 'description'),
            descriptionSubmitted: array_key_exists('description', $data),
            registryId: self::nullableInt($data, 'registry_id'),
            registryIdSubmitted: array_key_exists('registry_id', $data),
            referentId: self::nullableInt($data, 'referent_id'),
            referentIdSubmitted: array_key_exists('referent_id', $data),
            parentTaskId: self::nullableInt($data, 'parent_task_id'),
            parentTaskIdSubmitted: array_key_exists('parent_task_id', $data),
            taskTypeId: self::nullableInt($data, 'task_type_id'),
            taskTypeIdSubmitted: array_key_exists('task_type_id', $data),
            taskPriorityId: self::nullableInt($data, 'task_priority_id'),
            taskPriorityIdSubmitted: array_key_exists('task_priority_id', $data),
            taskImportanceId: self::nullableInt($data, 'task_importance_id'),
            taskImportanceIdSubmitted: array_key_exists('task_importance_id', $data),
            taskCategoryId: self::nullableInt($data, 'task_category_id'),
            taskCategoryIdSubmitted: array_key_exists('task_category_id', $data),
            opportunityId: self::nullableInt($data, 'opportunity_id'),
            opportunityIdSubmitted: array_key_exists('opportunity_id', $data),
            workOrderId: self::nullableInt($data, 'work_order_id'),
            workOrderIdSubmitted: array_key_exists('work_order_id', $data),
            workOrderStageId: self::nullableInt($data, 'work_order_stage_id'),
            workOrderStageIdSubmitted: array_key_exists('work_order_stage_id', $data),
            requesterId: self::nullableInt($data, 'requester_id'),
            requesterIdSubmitted: array_key_exists('requester_id', $data),
            startDate: self::nullableString($data, 'start_date'),
            startDateSubmitted: array_key_exists('start_date', $data),
            endDate: self::nullableString($data, 'end_date'),
            endDateSubmitted: array_key_exists('end_date', $data),
            startTime: self::nullableString($data, 'start_time'),
            startTimeSubmitted: array_key_exists('start_time', $data),
            endTime: self::nullableString($data, 'end_time'),
            endTimeSubmitted: array_key_exists('end_time', $data),
            estimatedMinutes: self::nullableInt($data, 'estimated_minutes'),
            estimatedMinutesSubmitted: array_key_exists('estimated_minutes', $data),
            closureFeedback: self::nullableString($data, 'closure_feedback'),
            closureFeedbackSubmitted: array_key_exists('closure_feedback', $data),
            assigneeIds: array_key_exists('assignee_ids', $data) ? self::normalizeIds($data['assignee_ids']) : null,
            watcherIds: array_key_exists('watcher_ids', $data) ? self::normalizeIds($data['watcher_ids']) : null,
            recurrence: isset($data['recurrence']) ? TaskRecurrenceData::fromValidated($data['recurrence']) : null,
            recurrenceSubmitted: array_key_exists('recurrence', $data),
        );
    }

    /**
     * Only the attributes the client actually submitted, ready for a partial
     * mass-assignment update.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        foreach (self::REQUIRED_WHEN_SUBMITTED as $column => $property) {
            if ($this->{$property} !== null) {
                $attributes[$column] = $this->{$property};
            }
        }

        foreach (self::NULLABLE_COLUMNS as $column => [$property, $flag]) {
            if ($this->{$flag}) {
                $attributes[$column] = $this->{$property};
            }
        }

        return $attributes;
    }

    public function hasAssigneeIds(): bool
    {
        return $this->assigneeIds !== null;
    }

    public function hasWatcherIds(): bool
    {
        return $this->watcherIds !== null;
    }

    public function hasRecurrence(): bool
    {
        return $this->recurrenceSubmitted;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableInt(array $data, string $key): ?int
    {
        return isset($data[$key]) ? (int) $data[$key] : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        return isset($data[$key]) ? (string) $data[$key] : null;
    }

    /**
     * @return array<int, int>
     */
    private static function normalizeIds(mixed $ids): array
    {
        return array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, (array) $ids)));
    }
}

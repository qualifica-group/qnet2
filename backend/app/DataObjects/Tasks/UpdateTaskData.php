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
 * `taskStatusId`, `isBlocked` and `requiresClosureFeedback` are
 * `sometimes|required`/`sometimes|boolean` at the FormRequest layer, so a
 * non-null value already means "submitted" and they need no flag of their
 * own.
 *
 * `assigneeIds`/`watcherIds` follow the collection convention instead: null
 * means "not submitted, leave the pivot untouched", an array — INCLUDING the
 * empty one — is an authoritative full-replace sync (AC-012).
 *
 * `creatorId` is GONE (D-10) and `completionPercentage` never existed (D-6):
 * both are `prohibited` at the FormRequest layer and never reach this DTO.
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
        'is_blocked' => 'isBlocked',
        'requires_closure_feedback' => 'requiresClosureFeedback',
    ];

    /**
     * Nullable columns, keyed by column name -> [value property, flag
     * property]: only the flag decides whether the column is written.
     *
     * @var array<string, array{string, string}>
     */
    private const array NULLABLE_COLUMNS = [
        'description' => ['description', 'descriptionSubmitted'],
        'registry_id' => ['registryId', 'registryIdSubmitted'],
        'referent_id' => ['referentId', 'referentIdSubmitted'],
        'parent_task_id' => ['parentTaskId', 'parentTaskIdSubmitted'],
        'task_type_id' => ['taskTypeId', 'taskTypeIdSubmitted'],
        'task_priority_id' => ['taskPriorityId', 'taskPriorityIdSubmitted'],
        'task_importance_id' => ['taskImportanceId', 'taskImportanceIdSubmitted'],
        'task_category_id' => ['taskCategoryId', 'taskCategoryIdSubmitted'],
        'opportunity_id' => ['opportunityId', 'opportunityIdSubmitted'],
        'work_order_id' => ['workOrderId', 'workOrderIdSubmitted'],
        'requester_id' => ['requesterId', 'requesterIdSubmitted'],
        'start_date' => ['startDate', 'startDateSubmitted'],
        'end_date' => ['endDate', 'endDateSubmitted'],
        'completion_date' => ['completionDate', 'completionDateSubmitted'],
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
        public ?bool $isBlocked = null,
        public ?bool $requiresClosureFeedback = null,
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
        public ?int $requesterId = null,
        public bool $requesterIdSubmitted = false,
        public ?string $startDate = null,
        public bool $startDateSubmitted = false,
        public ?string $endDate = null,
        public bool $endDateSubmitted = false,
        public ?string $completionDate = null,
        public bool $completionDateSubmitted = false,
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
            isBlocked: isset($data['is_blocked']) ? (bool) $data['is_blocked'] : null,
            requiresClosureFeedback: isset($data['requires_closure_feedback']) ? (bool) $data['requires_closure_feedback'] : null,
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
            requesterId: self::nullableInt($data, 'requester_id'),
            requesterIdSubmitted: array_key_exists('requester_id', $data),
            startDate: self::nullableString($data, 'start_date'),
            startDateSubmitted: array_key_exists('start_date', $data),
            endDate: self::nullableString($data, 'end_date'),
            endDateSubmitted: array_key_exists('end_date', $data),
            completionDate: self::nullableString($data, 'completion_date'),
            completionDateSubmitted: array_key_exists('completion_date', $data),
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

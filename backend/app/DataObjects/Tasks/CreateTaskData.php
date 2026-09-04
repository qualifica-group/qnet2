<?php

declare(strict_types=1);

namespace App\DataObjects\Tasks;

/**
 * Validated payload for creating a Task (POST /api/tasks, spec 0101).
 * Declared DTO (no "magic flying array") so the StoreTaskRequest ->
 * TaskService contract is explicit — see standards/architecture.md ->
 * Data Transfer Objects.
 *
 * `creatorId` is DELIBERATELY absent (D-10): the creator is never client
 * input, so it is not a payload concern at all — TaskService sets it from the
 * authenticated actor, and StoreTaskRequest rejects the key with
 * `prohibited` (AC-011). `completionPercentage` is absent for the same
 * reason in reverse (D-6): it is derived from the status, never written.
 *
 * `assigneeIds`/`watcherIds` default to `[]`, so a create with neither key
 * simply syncs two empty pivots. `startTime`/`endTime` are `H:i` strings
 * (D-11: `FieldDefinition` has no `time` type, so they travel as text
 * validated by `date_format`).
 */
final readonly class CreateTaskData
{
    /**
     * @param  array<int, int>  $assigneeIds
     * @param  array<int, int>  $watcherIds
     */
    public function __construct(
        public string $title,
        public int $taskStatusId,
        public ?string $description = null,
        public ?int $registryId = null,
        public ?int $referentId = null,
        public ?int $parentTaskId = null,
        public ?int $taskTypeId = null,
        public ?int $taskPriorityId = null,
        public ?int $taskImportanceId = null,
        public ?int $taskCategoryId = null,
        public ?int $opportunityId = null,
        public ?int $workOrderId = null,
        public ?int $requesterId = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?string $completionDate = null,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?int $estimatedMinutes = null,
        public bool $isBlocked = false,
        public bool $requiresClosureFeedback = false,
        public ?string $closureFeedback = null,
        public array $assigneeIds = [],
        public array $watcherIds = [],
    ) {}

    /**
     * Build from the validated StoreTaskRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            title: (string) $data['title'],
            taskStatusId: (int) $data['task_status_id'],
            description: self::nullableString($data, 'description'),
            registryId: self::nullableInt($data, 'registry_id'),
            referentId: self::nullableInt($data, 'referent_id'),
            parentTaskId: self::nullableInt($data, 'parent_task_id'),
            taskTypeId: self::nullableInt($data, 'task_type_id'),
            taskPriorityId: self::nullableInt($data, 'task_priority_id'),
            taskImportanceId: self::nullableInt($data, 'task_importance_id'),
            taskCategoryId: self::nullableInt($data, 'task_category_id'),
            opportunityId: self::nullableInt($data, 'opportunity_id'),
            workOrderId: self::nullableInt($data, 'work_order_id'),
            requesterId: self::nullableInt($data, 'requester_id'),
            startDate: self::nullableString($data, 'start_date'),
            endDate: self::nullableString($data, 'end_date'),
            completionDate: self::nullableString($data, 'completion_date'),
            startTime: self::nullableString($data, 'start_time'),
            endTime: self::nullableString($data, 'end_time'),
            estimatedMinutes: self::nullableInt($data, 'estimated_minutes'),
            isBlocked: (bool) ($data['is_blocked'] ?? false),
            requiresClosureFeedback: (bool) ($data['requires_closure_feedback'] ?? false),
            closureFeedback: self::nullableString($data, 'closure_feedback'),
            assigneeIds: self::normalizeIds($data['assignee_ids'] ?? []),
            watcherIds: self::normalizeIds($data['watcher_ids'] ?? []),
        );
    }

    /**
     * The mass-assignable column map. `creator_id` is NOT here (D-10) — it is
     * set on the model directly, since it is not part of Task's #[Fillable].
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'title' => $this->title,
            'task_status_id' => $this->taskStatusId,
            'description' => $this->description,
            'registry_id' => $this->registryId,
            'referent_id' => $this->referentId,
            'parent_task_id' => $this->parentTaskId,
            'task_type_id' => $this->taskTypeId,
            'task_priority_id' => $this->taskPriorityId,
            'task_importance_id' => $this->taskImportanceId,
            'task_category_id' => $this->taskCategoryId,
            'opportunity_id' => $this->opportunityId,
            'work_order_id' => $this->workOrderId,
            'requester_id' => $this->requesterId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'completion_date' => $this->completionDate,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'estimated_minutes' => $this->estimatedMinutes,
            'is_blocked' => $this->isBlocked,
            'requires_closure_feedback' => $this->requiresClosureFeedback,
            'closure_feedback' => $this->closureFeedback,
        ];
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

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
 * `isBlocked` is absent too (spec 0116 D-6): `is_blocked` is `prohibited` at
 * creation, so every new Task starts unblocked — attributes() hardcodes it.
 *
 * `taskStatusId` is GONE too (spec 0118 D-3): the initial status is derived
 * server-side by `App\Services\Tasks\TaskInitialStatusResolver` (D-4) from
 * `requesterId`/`assigneeIds`/the creator, so `StoreTaskRequest` rejects the
 * key with `prohibited` (AC-009) and this DTO carries no field for it —
 * `TaskService::create()` sets `task_status_id` on the model directly, the
 * same way it already sets `creator_id`.
 *
 * `requesterId`/`endDate` are non-nullable and `assigneeIds` keeps its
 * `min:1` floor at the type level too (spec 0118 D-1): all three are
 * `required` at the FormRequest layer, so a payload that reaches this DTO
 * has already guaranteed their presence — a nullable property here would
 * let a future caller construct an invalid state the FormRequest already
 * forbids. `watcherIds` stays optional and defaults to `[]`, so a create
 * with no observers simply syncs an empty pivot. `startTime`/`endTime` are
 * `H:i` strings (D-11: `FieldDefinition` has no `time` type, so they travel
 * as text validated by `date_format`).
 *
 * `recurrence` (spec 0120, D-3) is the one property with NO column of its
 * own on `tasks`: a non-null value tells TaskService::create() to hand it to
 * App\Services\Tasks\TaskRecurrenceService::set(), which creates the
 * `task_recurrences` row and links `task_recurrence_id` — hence it is absent
 * from attributes() below.
 */
final readonly class CreateTaskData
{
    /**
     * @param  array<int, int>  $assigneeIds
     * @param  array<int, int>  $watcherIds
     */
    public function __construct(
        public string $title,
        public int $requesterId,
        public string $endDate,
        public array $assigneeIds,
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
        public ?string $startDate = null,
        public ?string $startTime = null,
        public ?string $endTime = null,
        public ?int $estimatedMinutes = null,
        public bool $requiresClosureFeedback = false,
        public bool $requiresValidation = false,
        public ?string $closureFeedback = null,
        public array $watcherIds = [],
        public ?TaskRecurrenceData $recurrence = null,
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
            requesterId: (int) $data['requester_id'],
            endDate: (string) $data['end_date'],
            assigneeIds: self::normalizeIds($data['assignee_ids']),
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
            startDate: self::nullableString($data, 'start_date'),
            startTime: self::nullableString($data, 'start_time'),
            endTime: self::nullableString($data, 'end_time'),
            estimatedMinutes: self::nullableInt($data, 'estimated_minutes'),
            requiresClosureFeedback: (bool) ($data['requires_closure_feedback'] ?? false),
            requiresValidation: (bool) ($data['requires_validation'] ?? false),
            closureFeedback: self::nullableString($data, 'closure_feedback'),
            watcherIds: self::normalizeIds($data['watcher_ids'] ?? []),
            recurrence: isset($data['recurrence']) ? TaskRecurrenceData::fromValidated($data['recurrence']) : null,
        );
    }

    /**
     * The mass-assignable column map. `creator_id` and `task_status_id` are
     * NOT here (D-10/D-3 of spec 0118) — both are set on the model directly
     * by TaskService, since neither is part of Task's #[Fillable].
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'title' => $this->title,
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
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'estimated_minutes' => $this->estimatedMinutes,
            'is_blocked' => false,
            'requires_closure_feedback' => $this->requiresClosureFeedback,
            'requires_validation' => $this->requiresValidation,
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

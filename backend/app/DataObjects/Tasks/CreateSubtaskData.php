<?php

declare(strict_types=1);

namespace App\DataObjects\Tasks;

/**
 * One row of `subtasks[]` on POST /api/tasks (spec 0155, D-3). A DELIBERATELY
 * smaller shape than CreateTaskData: a sub-task never carries its own
 * `registry_id`/`referent_id`/`opportunity_id`/`work_order_id`/
 * `work_order_stage_id`/`lead_id`/`is_private`/`requester_id`/`recurrence` —
 * none of those keys exist in this row's shape at all, so there is no field
 * for them here; `App\Services\Tasks\TaskSubtaskBatchCreator` copies the
 * first seven straight off the just-created parent, forces
 * `work_order_stage_id` to null and derives the status the same way a plain
 * create does.
 *
 * `assigneeIds`/`watcherIds`/`taskTypeId`/`taskPriorityId`/`taskImportanceId`/
 * `endDate` are `null` when the row OMITS the key (the batch creator then
 * falls back to the parent's own resulting value) versus an actual submitted
 * value otherwise — `array_key_exists()` in `fromValidated()` is what tells
 * "omitted" apart from "submitted empty", never `??`. `taskCategoryId` is the
 * one field in the per-row shape that is NEITHER always-inherited nor
 * inherited-when-omitted (D-3): a plain optional column, null when absent.
 */
final readonly class CreateSubtaskData
{
    /**
     * @param  array<int, int>|null  $assigneeIds
     * @param  array<int, int>|null  $watcherIds
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $startDate = null,
        public ?string $endDate = null,
        public ?int $estimatedMinutes = null,
        public ?array $assigneeIds = null,
        public ?array $watcherIds = null,
        public ?int $taskTypeId = null,
        public ?int $taskPriorityId = null,
        public ?int $taskImportanceId = null,
        public ?int $taskCategoryId = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            title: (string) $data['title'],
            description: self::nullableString($data, 'description'),
            startDate: self::nullableString($data, 'start_date'),
            endDate: self::nullableString($data, 'end_date'),
            estimatedMinutes: self::nullableInt($data, 'estimated_minutes'),
            assigneeIds: array_key_exists('assignee_ids', $data) ? self::normalizeIds($data['assignee_ids']) : null,
            watcherIds: array_key_exists('watcher_ids', $data) ? self::normalizeIds($data['watcher_ids']) : null,
            taskTypeId: self::nullableInt($data, 'task_type_id'),
            taskPriorityId: self::nullableInt($data, 'task_priority_id'),
            taskImportanceId: self::nullableInt($data, 'task_importance_id'),
            taskCategoryId: self::nullableInt($data, 'task_category_id'),
        );
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

<?php

declare(strict_types=1);

namespace App\DataObjects\TaskTemplates;

/**
 * Validated payload for ONE row of a `task_templates` header (spec 0124,
 * D-1), carried inside Create/UpdateTaskTemplateData's own `items` array —
 * there is no dedicated endpoint for a row.
 *
 * `id` is null for a store request (D-1: `items.*.id` is `prohibited` there)
 * and for a brand-new row on update; a non-null `id` on update identifies an
 * existing row to update, already asserted to belong to the target template
 * by UpdateTaskTemplateRequest before this DTO is trusted
 * (App\Services\TaskTemplates\TaskTemplateItemWriter::sync).
 */
final readonly class TaskTemplateItemData
{
    public function __construct(
        public ?int $id,
        public string $title,
        public ?string $description,
        public ?int $estimatedMinutes,
        public ?int $taskStatusId,
        public int $dueOffsetDays,
    ) {}

    /**
     * Build from ONE validated row of the `items` array.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromValidated(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            title: (string) $row['title'],
            description: array_key_exists('description', $row) ? $row['description'] : null,
            estimatedMinutes: isset($row['estimated_minutes']) ? (int) $row['estimated_minutes'] : null,
            taskStatusId: isset($row['task_status_id']) ? (int) $row['task_status_id'] : null,
            dueOffsetDays: (int) ($row['due_offset_days'] ?? 0),
        );
    }

    /**
     * Mass-assignable attributes for this row, INCLUDING `sort_order` — the
     * submission index the writer places this row at (D-1: the array order
     * IS the order, no client-submitted sort_order field exists).
     * `description` is DELIBERATELY absent (spec 0128, D-2/D-3): the raw
     * HTML may carry inline `data:` URI images that need the row to already
     * have an id, so App\Services\TaskTemplates\TaskTemplateItemWriter sets
     * it via TaskTemplateDescriptionWriter instead of mass assignment.
     *
     * @return array<string, mixed>
     */
    public function attributes(int $sortOrder): array
    {
        return [
            'title' => $this->title,
            'estimated_minutes' => $this->estimatedMinutes,
            'task_status_id' => $this->taskStatusId,
            'due_offset_days' => $this->dueOffsetDays,
            'sort_order' => $sortOrder,
        ];
    }
}

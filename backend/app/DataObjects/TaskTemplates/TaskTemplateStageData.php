<?php

declare(strict_types=1);

namespace App\DataObjects\TaskTemplates;

/**
 * Validated payload for ONE "Fase" of a `task_templates` header (spec 0146,
 * D-2), carried inside Create/UpdateTaskTemplateData's own `stages` array —
 * there is no dedicated endpoint for a stage, the same convention as
 * TaskTemplateItemData.
 *
 * `key` is a REQUEST-SCOPED identifier only, never persisted: it is how
 * `items.*.stage_key` cross-references a row of THIS same `stages` array.
 * App\Services\TaskTemplates\TaskTemplateStageWriter::create()/sync() return
 * a `key => id` map that TaskTemplateItemData::attributes() resolves
 * against.
 *
 * `id` is null for a store request (Http\Requests\TaskTemplates\Concerns\
 * ValidatesTaskTemplateStages makes `stages.*.id` `prohibited` there) and for
 * a brand-new stage on update; a non-null `id` on update identifies an
 * existing row, already asserted to belong to the target template by
 * UpdateTaskTemplateRequest before this DTO is trusted.
 */
final readonly class TaskTemplateStageData
{
    public function __construct(
        public ?int $id,
        public string $key,
        public string $name,
    ) {}

    /**
     * Build from ONE validated row of the `stages` array.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromValidated(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            key: (string) $row['key'],
            name: (string) $row['name'],
        );
    }
}

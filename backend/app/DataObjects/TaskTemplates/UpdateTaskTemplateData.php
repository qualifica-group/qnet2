<?php

declare(strict_types=1);

namespace App\DataObjects\TaskTemplates;

/**
 * Validated payload for a partial (PATCH) task template update
 * (PUT/PATCH /api/task-templates/{taskTemplate}, spec 0124). Every field is
 * optional (partial update); `description` is a legitimately nullable VALUE
 * (it clears back to none), so a plain null property cannot distinguish "not
 * submitted" from "submitted as null" — the `descriptionSubmitted` flag
 * carries that distinction, mirroring UpdateProductTypologyData.
 *
 * `items`, when submitted, triggers a FULL sync (D-1) via
 * App\Services\TaskTemplates\TaskTemplateItemWriter::sync — `itemsSubmitted()`
 * is how the Service tells "items omitted, leave them alone" apart from
 * "items sent" (a null array can never mean the latter: the FormRequest
 * requires at least one row when the key is present).
 *
 * `stages` (spec 0146, D-2) follows the exact same "omitted vs sent" shape,
 * one level up: when submitted it triggers a full sync via
 * App\Services\TaskTemplates\TaskTemplateStageWriter::sync, resolved BEFORE
 * `items` so `items.*.stage_key` can map onto the fresh stage ids
 * (TaskTemplateService::update()).
 */
final readonly class UpdateTaskTemplateData
{
    /**
     * @param  array<int, TaskTemplateItemData>|null  $items
     * @param  array<int, TaskTemplateStageData>|null  $stages
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public bool $descriptionSubmitted = false,
        public ?bool $isActive = null,
        public ?array $items = null,
        public ?array $stages = null,
    ) {}

    /**
     * Build from the validated UpdateTaskTemplateRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        /** @var array<int, array<string, mixed>>|null $items */
        $items = array_key_exists('items', $data) ? $data['items'] : null;
        /** @var array<int, array<string, mixed>>|null $stages */
        $stages = array_key_exists('stages', $data) ? $data['stages'] : null;

        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            description: array_key_exists('description', $data) ? $data['description'] : null,
            descriptionSubmitted: array_key_exists('description', $data),
            isActive: array_key_exists('is_active', $data) ? (bool) $data['is_active'] : null,
            items: $items === null ? null : array_map(TaskTemplateItemData::fromValidated(...), $items),
            stages: $stages === null ? null : array_map(TaskTemplateStageData::fromValidated(...), $stages),
        );
    }

    /**
     * Only the header attributes the client actually submitted, ready for a
     * partial mass-assignment update. `description` is DELIBERATELY absent
     * (spec 0128, D-2/D-3/D-4): TaskTemplateService reads `description`/
     * `descriptionSubmitted` directly and hands them to
     * TaskTemplateDescriptionWriter instead of mass-assigning the raw HTML.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        if ($this->isActive !== null) {
            $attributes['is_active'] = $this->isActive;
        }

        return $attributes;
    }

    public function itemsSubmitted(): bool
    {
        return $this->items !== null;
    }

    public function stagesSubmitted(): bool
    {
        return $this->stages !== null;
    }
}

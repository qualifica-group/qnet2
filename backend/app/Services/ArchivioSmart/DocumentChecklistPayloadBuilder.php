<?php

declare(strict_types=1);

namespace App\Services\ArchivioSmart;

use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;

/**
 * Builds the ArchivioSmart PUT /checklists/{id} body from the current checklist state.
 */
class DocumentChecklistPayloadBuilder
{
    public const DATE_FIELD = 'issue_date';

    /**
     * @return array<string, mixed>
     */
    public function build(DocumentChecklist $checklist): array
    {
        return [
            'name' => $checklist->name,
            'code' => $checklist->code,
            'category_external_id' => $checklist->category_id === null ? null : (string) $checklist->category_id,
            'description' => $this->nullIfBlank($checklist->description),
            'is_active' => (bool) $checklist->is_active,
            'items' => $checklist->items
                ->sortBy('position')
                ->map(fn (DocumentChecklistItem $item): array => $this->buildItem($item))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildItem(DocumentChecklistItem $item): array
    {
        return [
            'external_id' => (string) $item->id,
            'name' => $item->name,
            'description' => $this->nullIfBlank($item->description),
            'section' => $this->nullIfBlank($item->section),
            'is_required' => (bool) $item->is_required,
            'allowed_file_types' => array_values($item->allowed_file_types ?? []),
            'max_file_size_kb' => (int) $item->max_file_size_kb,
            'min_files' => (int) $item->min_files,
            'max_files' => (int) $item->max_files,
            'validation_prompt' => $this->nullIfBlank($item->validation_prompt),
            'validation_rules' => $item->max_age_days
                ? ['max_age_days' => (int) $item->max_age_days, 'date_field' => self::DATE_FIELD]
                : null,
        ];
    }

    private function nullIfBlank(?string $value): ?string
    {
        return filled($value) ? $value : null;
    }
}

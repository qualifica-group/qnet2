<?php

declare(strict_types=1);

namespace App\Services\ArchivioSmart;

use App\Models\DocumentChecklist;
use App\Models\DocumentChecklistItem;
use App\Models\DocumentChecklistSection;
use Illuminate\Support\Collection;

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
        $sectionNames = $checklist->sections->pluck('name', 'id');

        return [
            'name' => $checklist->name,
            'code' => $checklist->code,
            'category_external_id' => $checklist->category_id === null ? null : (string) $checklist->category_id,
            'description' => $this->nullIfBlank($checklist->description),
            'is_active' => (bool) $checklist->is_active,
            'sections' => $checklist->sections
                ->sortBy('position')
                ->map(fn (DocumentChecklistSection $section): array => $this->buildSection($section))
                ->values()
                ->all(),
            'items' => $checklist->items
                ->sortBy('position')
                ->map(fn (DocumentChecklistItem $item): array => $this->buildItem($item, $sectionNames))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSection(DocumentChecklistSection $section): array
    {
        return [
            'external_id' => (string) $section->id,
            'name' => $section->name,
            'description' => $this->nullIfBlank($section->description),
            'sort_order' => (int) $section->position,
        ];
    }

    /**
     * `section` (the group name) is kept next to `section_external_id` for an
     * ArchivioSmart that does not read `sections` yet (spec 0427, D-1).
     *
     * @param  Collection<int, string>  $sectionNames  section name by id
     * @return array<string, mixed>
     */
    private function buildItem(DocumentChecklistItem $item, Collection $sectionNames): array
    {
        return [
            'external_id' => (string) $item->id,
            'name' => $item->name,
            'description' => $this->nullIfBlank($item->description),
            'section_external_id' => $item->section_id === null ? null : (string) $item->section_id,
            'section' => $item->section_id === null ? null : $sectionNames->get($item->section_id),
            'is_required' => (bool) $item->is_required,
            'allowed_file_types' => array_values($item->allowed_file_types ?? []),
            'max_file_size_kb' => (int) $item->max_file_size_kb,
            'min_files' => (int) $item->min_files,
            'max_files' => (int) $item->max_files,
            'validation_prompt' => $this->nullIfBlank($item->validation_prompt),
            'validation_rules' => $item->max_age_days
                ? ['max_age_days' => (int) $item->max_age_days, 'date_field' => $item->date_field ?? self::DATE_FIELD]
                : null,
        ];
    }

    private function nullIfBlank(?string $value): ?string
    {
        return filled($value) ? $value : null;
    }
}

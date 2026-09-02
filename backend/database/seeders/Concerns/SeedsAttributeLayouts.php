<?php

namespace Database\Seeders\Concerns;

use App\Enums\LayoutItemWidth;
use App\Enums\LayoutSectionVariant;

/**
 * Builds a layout blob's sections (spec 0062, layout-contract) for the seeders
 * that group the client's reference attributes into form sections — the
 * Product-context "Dati Aula" (QualificaClassroomLayoutSeeder) and the
 * "Dati Lavorazione Contatto" of the Offerta and Commessa contexts
 * (QualificaContactProcessingSeeder).
 *
 * Only the BLOB is built here: each seeder owns which categories it writes to
 * and injects AttributeLayoutService itself, because that Service validates
 * every `attribute_code` against the target category's own effective
 * attributes (an allow-list this trait cannot see).
 */
trait SeedsAttributeLayouts
{
    /**
     * Two fields per row (ui-design.md §2: compact by default), collapsing to
     * one column on a narrow panel through the renderer's container queries.
     */
    protected const int LAYOUT_SECTION_COLUMNS = 2;

    /**
     * @param  list<list<string>>  $rows  attribute codes, one inner list per rendered row
     * @return array<string, mixed>
     */
    protected function layoutSection(string $id, string $title, array $rows, int $sortOrder): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'description' => null,
            'variant' => LayoutSectionVariant::Default->value,
            'collapsible' => false,
            'default_collapsed' => false,
            'columns' => self::LAYOUT_SECTION_COLUMNS,
            'sort_order' => $sortOrder,
            'rows' => array_map(
                static fn (array $codes, int $index): array => [
                    // Deterministic ids: a re-seed of a reset category rebuilds
                    // the identical blob instead of churning random uuids.
                    'id' => sprintf('%s-%d', $id, $index),
                    'items' => array_map(
                        static fn (string $code): array => [
                            'attribute_code' => $code,
                            'width' => LayoutItemWidth::Half->value,
                        ],
                        $codes,
                    ),
                ],
                $rows,
                array_keys($rows),
            ),
        ];
    }

    /**
     * Keeps only the codes the target category actually resolves, then drops
     * the rows left empty — a category opting out of an ancestor's assignments
     * must not carry an item pointing at an attribute it does not have.
     *
     * @param  list<list<string>>  $rows
     * @param  list<string>  $allowedCodes
     * @return list<list<string>>
     */
    protected function keepAllowedCodes(array $rows, array $allowedCodes): array
    {
        $kept = array_map(
            static fn (array $row): array => array_values(array_intersect($row, $allowedCodes)),
            $rows,
        );

        return array_values(array_filter($kept, static fn (array $row): bool => $row !== []));
    }
}

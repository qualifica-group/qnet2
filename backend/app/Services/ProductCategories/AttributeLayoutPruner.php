<?php

declare(strict_types=1);

namespace App\Services\ProductCategories;

/**
 * Removes attributes from an attribute layout blob: the items whose code is
 * not kept go, then the rows and sections left empty; a section condition
 * whose controlling attribute is no longer placed is cleared (spec 0288), so
 * the pruned blob still passes AttributeLayoutValidator.
 */
final class AttributeLayoutPruner
{
    /**
     * @param  array<string, mixed>  $layout
     * @param  callable(string|null): bool  $keep  whether an item's `attribute_code` stays
     * @return array{sections: array<int, array<string, mixed>>}
     */
    public static function prune(array $layout, callable $keep): array
    {
        $sections = [];

        foreach ($layout['sections'] ?? [] as $section) {
            $rows = [];

            foreach ($section['rows'] ?? [] as $row) {
                $items = array_values(array_filter(
                    $row['items'] ?? [],
                    static fn (array $item): bool => $keep($item['attribute_code'] ?? null),
                ));

                if ($items !== []) {
                    $rows[] = [...$row, 'items' => $items];
                }
            }

            if ($rows !== []) {
                $sections[] = [...$section, 'rows' => $rows];
            }
        }

        return ['sections' => self::clearOrphanConditions($sections)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private static function clearOrphanConditions(array $sections): array
    {
        $placed = [];

        foreach ($sections as $section) {
            foreach ($section['rows'] as $row) {
                foreach ($row['items'] as $item) {
                    $placed[$item['attribute_code']] = true;
                }
            }
        }

        return array_map(static function (array $section) use ($placed): array {
            $controlCode = $section['visible_when']['attribute_code'] ?? null;

            return $controlCode !== null && ! isset($placed[$controlCode])
                ? [...$section, 'visible_when' => null]
                : $section;
        }, $sections);
    }
}

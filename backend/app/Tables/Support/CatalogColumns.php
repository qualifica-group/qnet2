<?php

namespace App\Tables\Support;

/**
 * Column descriptors shared by the plain catalogue grids (spec 0271): pure
 * data. `$prefix` is the frontend i18n namespace (e.g. `jobRoles.columns`).
 */
final class CatalogColumns
{
    /**
     * @return array<string, mixed>
     */
    public static function name(string $prefix): array
    {
        return [
            'id' => 'name',
            'label' => "{$prefix}.name",
            'type' => 'text',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'text',
            'searchable' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function description(string $prefix): array
    {
        return [
            'id' => 'description',
            'label' => "{$prefix}.description",
            'type' => 'text',
            'visible' => true,
            'sortable' => false,
            'filterable' => true,
            'filterType' => 'text',
        ];
    }

    /**
     * A to-many relation rendered as tags: no single sort key, no filter.
     *
     * @return array<string, mixed>
     */
    public static function tags(string $id, string $prefix): array
    {
        return [
            'id' => $id,
            'label' => "{$prefix}.{$id}",
            'type' => 'tags',
            'visible' => true,
            'sortable' => false,
            'filterable' => false,
        ];
    }

    /**
     * An aggregate column backed by a `withCount` alias: sortable, not filterable.
     *
     * @return array<string, mixed>
     */
    public static function count(string $id, string $prefix): array
    {
        return [
            'id' => $id,
            'label' => "{$prefix}.{$id}",
            'type' => 'number',
            'visible' => true,
            'sortable' => true,
            'filterable' => false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function textFilters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'description', 'type' => 'text'],
        ];
    }
}

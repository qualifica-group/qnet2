<?php

namespace App\Tables\Sectors;

/**
 * Declarative column/filter/action catalogue for the `sectors` domain
 * (spec 0018). Extracted out of SectorsTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic), mirroring
 * ProductCategoryColumnCatalog.
 *
 * `name`/`created_at` are real DB columns handled entirely by the generic
 * engine. `parent` has no real DB column of its own (it is the related
 * parent sector's name) and is DERIVED, mirroring
 * ProductCategoriesTableDefinition's `parent` column.
 */
final class SectorColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'sectors.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                // The parent sector's name, derived from the self-referencing
                // parent() relation. Sorted via a correlated subquery, filtered
                // via whereHas (both in the definition). Root sectors (no
                // parent) surface as null/empty.
                'id' => 'parent',
                'label' => 'sectors.columns.parent',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Spec 0212 D-7: the node's OWN flag (a real column); the
                // effective value inherited from an inactive ancestor is not
                // derived here.
                'id' => 'is_active',
                'label' => 'sectors.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'created_at',
                'label' => 'sectors.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'parent', 'type' => 'set'],
            ['columnId' => 'is_active', 'type' => 'boolean'],
            ['columnId' => 'created_at', 'type' => 'date'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            [
                'key' => 'view',
                'label' => 'actions.view',
                'icon' => 'eye',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'sectors.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'sectors.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'sectors.viewActivity',
            ],
        ];
    }
}

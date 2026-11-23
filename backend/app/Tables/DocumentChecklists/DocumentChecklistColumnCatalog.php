<?php

declare(strict_types=1);

namespace App\Tables\DocumentChecklists;

/**
 * Declarative column/filter/action catalogue of the `document-checklists`
 * domain (spec 0403): pure data, no logic. `items_count` is an AGGREGATE column
 * (baseQuery()'s `withCount('items')`): sortable, not filterable. `sync_error`
 * is a hidden column the grid reads for the status tooltip.
 */
final class DocumentChecklistColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'documentChecklists.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                // Derived from the category relation (DocumentChecklistsTableDefinition).
                'id' => 'category_name',
                'label' => 'documentChecklists.columns.category_name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'code',
                'label' => 'documentChecklists.columns.code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'is_active',
                'label' => 'documentChecklists.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'items_count',
                'label' => 'documentChecklists.columns.items_count',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
            [
                'id' => 'sync_status',
                'label' => 'documentChecklists.columns.sync_status',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'sync_error',
                'label' => 'documentChecklists.columns.sync_error',
                'type' => 'text',
                'visible' => false,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'synced_at',
                'label' => 'documentChecklists.columns.synced_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
            [
                'id' => 'created_at',
                'label' => 'documentChecklists.columns.created_at',
                'type' => 'datetime',
                'visible' => false,
                'sortable' => true,
                'filterable' => false,
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
            ['columnId' => 'code', 'type' => 'text'],
            ['columnId' => 'category_name', 'type' => 'text'],
            ['columnId' => 'is_active', 'type' => 'boolean'],
            ['columnId' => 'sync_status', 'type' => 'set'],
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
                'permission' => 'document-checklists.view',
            ],
            [
                'key' => 'sync',
                'label' => 'documentChecklists.actions.syncAgain',
                'icon' => 'refresh-cw',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'document-checklists.update',
            ],
            [
                // The frontend relabels it Activate/Deactivate from the row's is_active.
                'key' => 'activate',
                'label' => 'documentChecklists.actions.activate',
                'icon' => 'power',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'document-checklists.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'document-checklists.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'document-checklists.viewActivity',
            ],
        ];
    }
}

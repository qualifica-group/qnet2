<?php

declare(strict_types=1);

namespace App\Tables\DocumentChecklistCategories;

/**
 * Declarative column/filter/action catalogue of the `document-checklist-categories`
 * domain (spec 0410): pure data, no logic. `checklists_count` is an AGGREGATE column
 * (baseQuery()'s `withCount('checklists')`): sortable, not filterable. `sync_error`
 * is a hidden column the grid reads for the status tooltip. The actions follow the
 * `document-checklists.*` permissions (D-2).
 */
final class DocumentChecklistCategoryColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'documentChecklistCategories.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'description',
                'label' => 'documentChecklistCategories.columns.description',
                'type' => 'text',
                'visible' => false,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'sort_order',
                'label' => 'documentChecklistCategories.columns.sort_order',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
            [
                'id' => 'checklists_count',
                'label' => 'documentChecklistCategories.columns.checklists_count',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
            [
                'id' => 'sync_status',
                'label' => 'documentChecklistCategories.columns.sync_status',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'sync_error',
                'label' => 'documentChecklistCategories.columns.sync_error',
                'type' => 'text',
                'visible' => false,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'synced_at',
                'label' => 'documentChecklistCategories.columns.synced_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
            [
                'id' => 'created_at',
                'label' => 'documentChecklistCategories.columns.created_at',
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

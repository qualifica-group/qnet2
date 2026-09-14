<?php

declare(strict_types=1);

namespace App\Tables\TaskTemplates;

/**
 * Declarative column/filter/action catalogue for the `task-templates` domain
 * (spec 0124, AC-008). Extracted out of TaskTemplatesTableDefinition
 * (file-size split, engineering.md §6): pure data (no logic), mirroring
 * ProductTypologyColumnCatalog. `name`/`description`/`is_active`/
 * `created_at`/`updated_at` are real DB columns handled entirely by the
 * generic engine; `items_count` is an AGGREGATE column (withCount(), no real
 * DB column), delegated to TaskTemplateItemsCountColumn.
 */
final class TaskTemplateColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'taskTemplates.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'description',
                'label' => 'taskTemplates.columns.description',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'items_count',
                'label' => 'taskTemplates.columns.items_count',
                'type' => 'number',
                'visible' => true,
                // ORDER BY the withCount('items') alias — handled generically
                // by the engine, no applyDerivedSort override needed.
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'is_active',
                'label' => 'taskTemplates.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'created_at',
                'label' => 'taskTemplates.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'updated_at',
                'label' => 'taskTemplates.columns.updated_at',
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
            ['columnId' => 'description', 'type' => 'text'],
            ['columnId' => 'items_count', 'type' => 'number'],
            ['columnId' => 'is_active', 'type' => 'boolean'],
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'updated_at', 'type' => 'date'],
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
                'permission' => 'task-templates.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'task-templates.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'task-templates.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'task-templates.viewActivity',
            ],
        ];
    }
}

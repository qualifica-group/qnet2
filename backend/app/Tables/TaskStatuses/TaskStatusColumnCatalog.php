<?php

declare(strict_types=1);

namespace App\Tables\TaskStatuses;

use App\Enums\TaskStatusGroup;

/**
 * Declarative column/filter/action catalogue for the `task-statuses` domain (spec
 * 0101, D-4). Extracted out of TaskStatusesTableDefinition (file-size split,
 * engineering.md §6): pure data, no logic. Every column is a real DB column
 * handled entirely by the generic engine.
 *
 * `color` and `icon` are deliberately neither sortable nor filterable: they
 * are a swatch and a glyph name, not meaningful ordering/filter axes
 * (mirrors ContractStatusColumnCatalog's own choice for `color`).
 * `description` is filterable but not sortable (free text).
 * `completion_percentage` (D-6) is a real column here — it is the projection
 * every Task in this status reads (AC-021), so it is both sortable and
 * filterable. `group` (D-5) is a `set` filter over the fixed phase list, the
 * same shape ContractStatusColumnCatalog gives its own: the options come
 * from the enum, never from a hand-kept copy. `system_key` is NOT a column:
 * it rides on mapRow() only, so the grid can hide the delete action on the
 * three protected rows (AC-042).
 */
final class TaskStatusColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'taskStatuses.columns.name',
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
                'label' => 'taskStatuses.columns.description',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'color',
                'label' => 'taskStatuses.columns.color',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'icon',
                'label' => 'taskStatuses.columns.icon',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'group',
                'label' => 'taskStatuses.columns.group',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'completion_percentage',
                'label' => 'taskStatuses.columns.completion_percentage',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'sort_order',
                'label' => 'taskStatuses.columns.sort_order',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'is_active',
                'label' => 'taskStatuses.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'created_at',
                'label' => 'taskStatuses.columns.created_at',
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
            ['columnId' => 'group', 'type' => 'set', 'options' => TaskStatusGroup::values()],
            ['columnId' => 'completion_percentage', 'type' => 'number'],
            ['columnId' => 'sort_order', 'type' => 'number'],
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
                'permission' => 'task-statuses.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'task-statuses.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'task-statuses.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'task-statuses.viewActivity',
            ],
        ];
    }
}

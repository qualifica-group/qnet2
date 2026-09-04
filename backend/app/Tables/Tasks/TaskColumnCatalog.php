<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

/**
 * Declarative column/filter/action catalogue for the `tasks` domain (spec
 * 0101). Extracted out of TasksTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic), mirroring
 * WorkOrderColumnCatalog/ContractColumnCatalog.
 *
 * Three families live here:
 *  - REAL `tasks` columns (`title`, `start_date`, `end_date`,
 *    `completion_date`, `estimated_minutes`, `is_blocked`), handled entirely
 *    by the generic engine;
 *  - RELATION columns (the five configurators plus registry/opportunity/
 *    work order/richiedente/creatore, and the two user pivots
 *    assegnatari/osservatori), resolved by TaskRelationColumns;
 *  - the DERIVED trio: `completion_percentage` (D-6, owned by
 *    TaskStatusResolver — sorted through a correlated subquery, never a raw
 *    ORDER BY fragment) and the two hierarchy booleans `has_subtasks`/
 *    `is_subtask` (D-12).
 *
 * The four columns declared `visible: false` (`watchers`, `completion_date`,
 * `has_subtasks`, `is_subtask`) exist because the data_contract's `<delta>`
 * asks for their FILTERS while listing them outside the default column set:
 * in this engine a filter hangs off a column, so they are declared and left
 * hidden, toggleable from the column picker (AC-071).
 *
 * A to-many (`assignees`/`watchers`) and a boolean with no single sort key
 * (`has_subtasks`/`is_subtask`) are `sortable: false`; every derived column
 * with no discrete value list to `SELECT DISTINCT` declares
 * `hasFilterValues: false`.
 */
final class TaskColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'title',
                'label' => 'tasks.columns.title',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            self::relationColumn('registry', 'tasks.columns.registry'),
            self::relationColumn('task_type', 'tasks.columns.task_type'),
            self::relationColumn('task_status', 'tasks.columns.task_status'),
            self::relationColumn('task_priority', 'tasks.columns.task_priority'),
            self::relationColumn('task_importance', 'tasks.columns.task_importance'),
            self::relationColumn('task_category', 'tasks.columns.task_category'),
            [
                'id' => 'start_date',
                'label' => 'tasks.columns.start_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'end_date',
                'label' => 'tasks.columns.end_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            self::relationColumn('requester', 'tasks.columns.requester'),
            // To-many over `task_assignee`: no single row to ORDER BY, the
            // same shape the Offerta's own `managers` column has.
            self::relationColumn('assignees', 'tasks.columns.assignees', sortable: false),
            [
                // Derived from the status (D-6), never a `tasks` column:
                // sorted via a correlated subquery and filtered through the
                // status relation, both owned by TaskStatusResolver.
                'id' => 'completion_percentage',
                'label' => 'tasks.columns.completion_percentage',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
                'hasFilterValues' => false,
            ],
            [
                'id' => 'estimated_minutes',
                'label' => 'tasks.columns.estimated_minutes',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
                'hasFilterValues' => false,
            ],
            [
                'id' => 'is_blocked',
                'label' => 'tasks.columns.is_blocked',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            self::relationColumn('opportunity', 'tasks.columns.opportunity'),
            self::relationColumn('work_order', 'tasks.columns.work_order'),
            self::relationColumn('creator', 'tasks.columns.creator'),
            // Hidden by default — declared for their filters (see docblock).
            self::relationColumn('watchers', 'tasks.columns.watchers', sortable: false, visible: false),
            [
                'id' => 'completion_date',
                'label' => 'tasks.columns.completion_date',
                'type' => 'date',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            self::hierarchyColumn('has_subtasks', 'tasks.columns.has_subtasks'),
            self::hierarchyColumn('is_subtask', 'tasks.columns.is_subtask'),
        ];
    }

    /**
     * A relation-labelled column: rendered from the `{id, name, color, icon}`
     * object TasksTableDefinition::mapRow() emits (the five configurators
     * carry the badge attributes), `set`-filtered on the related row's own
     * name and sorted through a correlated subquery.
     *
     * @return array<string, mixed>
     */
    private static function relationColumn(string $id, string $label, bool $sortable = true, bool $visible = true): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => $visible,
            'sortable' => $sortable,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * A derived hierarchy boolean (D-12): a predicate on `parent_task_id`,
     * with no real column to sort or enumerate.
     *
     * @return array<string, mixed>
     */
    private static function hierarchyColumn(string $id, string $label): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'boolean',
            'visible' => false,
            'sortable' => false,
            'filterable' => true,
            'filterType' => 'boolean',
            'hasFilterValues' => false,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'title', 'type' => 'text'],
            ['columnId' => 'registry', 'type' => 'set'],
            ['columnId' => 'task_type', 'type' => 'set'],
            ['columnId' => 'task_status', 'type' => 'set'],
            ['columnId' => 'task_priority', 'type' => 'set'],
            ['columnId' => 'task_importance', 'type' => 'set'],
            ['columnId' => 'task_category', 'type' => 'set'],
            ['columnId' => 'start_date', 'type' => 'date'],
            ['columnId' => 'end_date', 'type' => 'date'],
            ['columnId' => 'completion_date', 'type' => 'date'],
            ['columnId' => 'requester', 'type' => 'set'],
            ['columnId' => 'creator', 'type' => 'set'],
            ['columnId' => 'assignees', 'type' => 'set'],
            ['columnId' => 'watchers', 'type' => 'set'],
            ['columnId' => 'opportunity', 'type' => 'set'],
            ['columnId' => 'work_order', 'type' => 'set'],
            ['columnId' => 'completion_percentage', 'type' => 'number'],
            ['columnId' => 'estimated_minutes', 'type' => 'number'],
            ['columnId' => 'is_blocked', 'type' => 'boolean'],
            ['columnId' => 'has_subtasks', 'type' => 'boolean'],
            ['columnId' => 'is_subtask', 'type' => 'boolean'],
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
                'permission' => 'tasks.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'tasks.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'tasks.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'tasks.viewActivity',
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

/**
 * Declarative column/filter/action catalogue for the `tasks` domain (spec
 * 0101, extended by spec 0156 for q-net alignment). Extracted out of
 * TasksTableDefinition (file-size split, engineering.md §6): pure data (no
 * logic), mirroring WorkOrderColumnCatalog/ContractColumnCatalog.
 *
 * Four families live here:
 *  - REAL `tasks` columns (`title`, `start_date`, `end_date`,
 *    `completion_date`, `estimated_minutes`, `is_blocked`, `updated_at`,
 *    `is_recurring`), handled entirely by the generic engine;
 *  - RELATION columns (the five configurators plus registry/opportunity/
 *    work order/richiedente/creatore/`work_order_stage`, and the two user
 *    pivots assegnatari/osservatori), resolved by TaskRelationColumns;
 *  - the two AGGREGATE columns with no real `tasks` column behind them
 *    (spec 0156, D-2), resolved by TaskAggregateColumns via a correlated
 *    subquery: `actual_minutes` (the segnatempo total) and `parent_title`
 *    (the parent Task's own title);
 *  - the DERIVED trio: `completion_percentage` (D-6, owned by
 *    TaskStatusResolver — sorted through a correlated subquery, never a raw
 *    ORDER BY fragment) and the two hierarchy booleans `has_subtasks`/
 *    `is_subtask` (D-12).
 *
 * `editable`/`editor`/`editableField`/`relation`/`nullable` (spec 0156, D-8)
 * are a UI HINT only (ResolvesEditableColumns::editableColumnIds()):
 * TaskCellWriter re-derives every guard against the real row regardless.
 *
 * `watchers`, `completion_date`, `has_subtasks`, `is_subtask`,
 * `work_order_stage`, `updated_at`, `is_recurring`, `parent_title` are
 * hidden by default, toggleable from the column picker (AC-071); every other
 * column is visible.
 *
 * A to-many (`assignees`/`watchers`) and a boolean with no single sort key
 * (`has_subtasks`/`is_subtask`/`is_recurring`) are `sortable: false`; every
 * derived/aggregate column with no discrete value list to `SELECT DISTINCT`
 * declares `hasFilterValues: false`.
 */
final class TaskColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            // Overrides InjectsDefaultIdColumn's own default (hidden,
            // sortable, NOT searchable): spec 0156, D-1 wants the quick
            // search to also match an exact numeric id
            // (TasksTableDefinition::applyDerivedSearch()).
            [
                'id' => 'id',
                'label' => 'table.columns.id',
                'type' => 'number',
                'visible' => false,
                'sortable' => true,
                'filterable' => false,
                'filterType' => null,
                'searchable' => true,
            ],
            [
                'id' => 'title',
                'label' => 'tasks.columns.title',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
                'editable' => true,
                'nullable' => false,
            ],
            self::relationColumn('registry', 'tasks.columns.registry'),
            self::editableRelationColumn('task_type', 'tasks.columns.task_type', 'task-types', 'task_type_id', nullable: true),
            self::editableRelationColumn('task_status', 'tasks.columns.task_status', 'task-statuses', 'task_status_id', nullable: false),
            self::editableRelationColumn('task_priority', 'tasks.columns.task_priority', 'task-priorities', 'task_priority_id', nullable: true),
            self::editableRelationColumn('task_importance', 'tasks.columns.task_importance', 'task-importances', 'task_importance_id', nullable: true),
            self::relationColumn('task_category', 'tasks.columns.task_category'),
            [
                'id' => 'start_date',
                'label' => 'tasks.columns.start_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                'editable' => true,
                'nullable' => true,
            ],
            [
                'id' => 'end_date',
                'label' => 'tasks.columns.end_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                'editable' => true,
                'nullable' => false,
            ],
            self::editableRelationColumn('requester', 'tasks.columns.requester', 'users', 'requester_id', nullable: false),
            // To-many over `task_assignee`/`task_watcher`: no single row to
            // ORDER BY, the same shape the Offerta's own `managers` column
            // has — multiselect editors instead of the single-id `relation`
            // editor (spec 0156, D-8).
            self::editableMultiselectColumn('assignees', 'tasks.columns.assignees', 'users', 'assignee_ids'),
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
                'editable' => true,
                'nullable' => true,
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
            self::editableRelationColumn('work_order', 'tasks.columns.work_order', 'work-orders', 'work_order_id', nullable: true),
            self::relationColumn('creator', 'tasks.columns.creator'),
            // Hidden by default — declared for their filters (see docblock).
            self::editableMultiselectColumn('watchers', 'tasks.columns.watchers', 'users', 'watcher_ids', visible: false),
            [
                'id' => 'completion_date',
                'label' => 'tasks.columns.completion_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            self::hierarchyColumn('has_subtasks', 'tasks.columns.has_subtasks'),
            self::hierarchyColumn('is_subtask', 'tasks.columns.is_subtask'),
            // Visible/filterable/sortable since spec 0156, D-2 (previously
            // sortable-only for spec 0153's own default sort).
            [
                'id' => 'updated_at',
                'label' => 'tasks.columns.updated_at',
                'type' => 'datetime',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            // Spec 0156, D-2: the total segnatempo logged on the Task by
            // every assignee (App\Tables\Tasks\TaskAggregateColumns).
            [
                'id' => 'actual_minutes',
                'label' => 'tasks.columns.actual_minutes',
                'type' => 'number',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
                'hasFilterValues' => false,
            ],
            // Spec 0156, D-2: true when this Task belongs to a recurrence
            // series (`task_recurrence_id` not null) — a plain boolean
            // predicate on a real FK, no derived-column hook needed.
            [
                'id' => 'is_recurring',
                'label' => 'tasks.columns.is_recurring',
                'type' => 'boolean',
                'visible' => false,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'boolean',
                'hasFilterValues' => false,
            ],
            // Spec 0156, D-2 (App\Tables\Tasks\TaskAggregateColumns).
            [
                'id' => 'parent_title',
                'label' => 'tasks.columns.parent_title',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'hasFilterValues' => false,
            ],
            // Spec 0156, D-2/D-8: the "Fase" this root Task sits in on its
            // commessa's task board — same relation shape as the other ten
            // (TaskRelationColumns::SIMPLE_RELATIONS), editable through the
            // dedicated `work_order_stage` editor (its options are the OPEN
            // stages of the ROW's own commessa, resolved client-side by
            // `useTaskWorkOrderStageOptions`, never a static/for-select list).
            [
                'id' => 'work_order_stage',
                'label' => 'tasks.columns.work_order_stage',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'editable' => true,
                'editor' => 'work_order_stage',
                'editableField' => 'work_order_stage_id',
                'nullable' => true,
            ],
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
     * A single-id relation column with inline editing (spec 0156, D-8):
     * `editor` defaults to `relation` (ResolvesColumnConfig) once `relation`
     * is declared, and `editableField` is the WRITE column
     * (spec 0054, D-1) — the column id itself stays the display id.
     *
     * @return array<string, mixed>
     */
    private static function editableRelationColumn(string $id, string $label, string $resource, string $editableField, bool $nullable): array
    {
        return [
            ...self::relationColumn($id, $label),
            'editable' => true,
            'relation' => ['resource' => $resource],
            'editableField' => $editableField,
            'nullable' => $nullable,
        ];
    }

    /**
     * A to-many relation column with inline editing as a full-replace sync
     * (spec 0156, D-8; user directive 2026-07-23's MULTISELECT_EDITOR
     * shape): unlike `editableRelationColumn()` the value is a LIST of ids,
     * so `editor` is declared explicitly rather than left to the `relation`
     * default.
     *
     * @return array<string, mixed>
     */
    private static function editableMultiselectColumn(string $id, string $label, string $resource, string $editableField, bool $visible = true): array
    {
        return [
            ...self::relationColumn($id, $label, sortable: false, visible: $visible),
            'editable' => true,
            'editor' => 'multiselect',
            'relation' => ['resource' => $resource],
            'editableField' => $editableField,
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
            ['columnId' => 'work_order_stage', 'type' => 'set'],
            ['columnId' => 'completion_percentage', 'type' => 'number'],
            ['columnId' => 'estimated_minutes', 'type' => 'number'],
            ['columnId' => 'is_blocked', 'type' => 'boolean'],
            ['columnId' => 'has_subtasks', 'type' => 'boolean'],
            ['columnId' => 'is_subtask', 'type' => 'boolean'],
            ['columnId' => 'updated_at', 'type' => 'date'],
            ['columnId' => 'actual_minutes', 'type' => 'number'],
            ['columnId' => 'is_recurring', 'type' => 'boolean'],
            ['columnId' => 'parent_title', 'type' => 'text'],
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
            // Spec 0156, D-4/D-5: opens the create form precompiled from the
            // row (no new endpoint — FE-only), gated the same as the form
            // itself (create + view the source row).
            [
                'key' => 'duplicate',
                'label' => 'actions.duplicate',
                'icon' => 'copy',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'tasks.create',
            ],
            [
                'key' => 'complete',
                'label' => 'tasks.actions.complete',
                'icon' => 'check',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'tasks.complete',
            ],
            [
                'key' => 'uncomplete',
                'label' => 'tasks.actions.uncomplete',
                'icon' => 'rotate-ccw',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'tasks.complete',
            ],
            [
                'key' => 'approve',
                'label' => 'tasks.actions.approve',
                'icon' => 'badge-check',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'tasks.validate',
            ],
            [
                'key' => 'reject',
                'label' => 'tasks.actions.reject',
                'icon' => 'badge-x',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'tasks.validate',
            ],
            [
                'key' => 'block',
                'label' => 'tasks.actions.block',
                'icon' => 'lock',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'tasks.block',
            ],
            [
                'key' => 'unblock',
                'label' => 'tasks.actions.unblock',
                'icon' => 'lock-open',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'tasks.block',
            ],
            [
                'key' => 'request_update',
                'label' => 'tasks.actions.request_update',
                'icon' => 'message-circle-question',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'tasks.requestUpdate',
            ],
            // Spec 0156, D-5: opens a panel with the Task's NotesSection; the
            // badge is the row's own `notes_count` (mirrors
            // OpportunityColumnCatalog/QuoteColumnCatalog).
            [
                'key' => 'notes',
                'label' => 'actions.notes',
                'icon' => 'messages-square',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'tasks.view',
                'count_field' => 'notes_count',
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

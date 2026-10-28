<?php

namespace App\Tables\WorkOrderWorkflows;

/**
 * Declarative column/filter/action catalogue for the `work-order-workflows`
 * domain (spec 0322, twin of quote-workflows). Extracted out
 * of WorkOrderWorkflowsTableDefinition (file-size split, engineering.md §6):
 * pure data (no logic). `criteria_fields`/`criteria_values`/`statuses_count`
 * are DERIVED (no real DB column — resolved by mapRow from the eager-loaded
 * `criteria`/`statuses` relations), so they are neither sortable nor
 * filterable.
 */
final class WorkOrderWorkflowColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'workOrderWorkflows.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'criteria_fields',
                'label' => 'workOrderWorkflows.columns.criteriaFields',
                'type' => 'tags',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'criteria_values',
                'label' => 'workOrderWorkflows.columns.criteriaValues',
                'type' => 'tags',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'statuses_count',
                'label' => 'workOrderWorkflows.columns.statusesCount',
                'type' => 'number',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'is_active',
                'label' => 'workOrderWorkflows.columns.isActive',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'updated_at',
                'label' => 'workOrderWorkflows.columns.updatedAt',
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
            ['columnId' => 'is_active', 'type' => 'boolean'],
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
                'permission' => 'work-order-workflows.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'work-order-workflows.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'work-order-workflows.viewActivity',
            ],
        ];
    }
}

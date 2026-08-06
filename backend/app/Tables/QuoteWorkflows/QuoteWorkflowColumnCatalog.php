<?php

namespace App\Tables\QuoteWorkflows;

/**
 * Declarative column/filter/action catalogue for the `quote-workflows`
 * domain (spec 0047, moved onto the Offerta by spec 0083 D-6). Extracted out
 * of QuoteWorkflowsTableDefinition (file-size split, engineering.md §6):
 * pure data (no logic). `criteria_fields`/`criteria_values`/`statuses_count`
 * are DERIVED (no real DB column — resolved by mapRow from the eager-loaded
 * `criteria`/`statuses` relations), so they are neither sortable nor
 * filterable.
 */
final class QuoteWorkflowColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'quoteWorkflows.columns.name',
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
                'label' => 'quoteWorkflows.columns.criteriaFields',
                'type' => 'tags',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'criteria_values',
                'label' => 'quoteWorkflows.columns.criteriaValues',
                'type' => 'tags',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'statuses_count',
                'label' => 'quoteWorkflows.columns.statusesCount',
                'type' => 'number',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'is_active',
                'label' => 'quoteWorkflows.columns.isActive',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'updated_at',
                'label' => 'quoteWorkflows.columns.updatedAt',
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
                'permission' => 'quote-workflows.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'quote-workflows.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'quote-workflows.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'quote-workflows.viewActivity',
            ],
        ];
    }
}

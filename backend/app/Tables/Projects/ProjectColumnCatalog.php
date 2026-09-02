<?php

namespace App\Tables\Projects;

/**
 * Declarative column/filter/action catalogue for the `projects` domain (spec
 * 0023). Extracted out of ProjectsTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic).
 *
 * `code`/`name`/`start_date`/`end_date`/`total_budget`/`target_lead`/
 * `created_at` are real DB columns handled entirely by the generic engine.
 * `pipeline_status`/`country`/`state`/`province`/`city`/`partner` have no
 * real column of their own (each is the related row's name) and are
 * DERIVED, resolved by ProjectsTableDefinition — only `pipeline_status` is
 * sortable (a correlated subquery per spec 0023 table_definitions); the rest
 * are filterable-only. `geo_scope` (spec 0027, D-2) is a purely COMPUTED,
 * DISPLAY-ONLY column: neither sortable nor filterable (no real value to
 * join/sort on), mirroring PipelineStatusColumnCatalog's `color`.
 *
 * `business_function`/`product_category` (spec 0094) are AGGREGATED
 * (to-many, via `productLines`) columns, resolved by ProjectRelationColumns
 * — filterable (`set` widget) but never sortable, same as before their
 * amendment: no shape change to this catalogue, only to how
 * ProjectsTableDefinition resolves them.
 */
final class ProjectColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'code',
                'label' => 'projects.columns.code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'name',
                'label' => 'projects.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
                // Inline cell-editing (spec 0053): real column, in
                // Project::$fillable, matches the mandatory `name` field key
                // in ProjectsAuthorization. `code` is NOT editable inline
                // (create-only, permanently read-only once persisted — spec
                // 0025 BR-1; also excluded from #[Fillable]).
                'editable' => true,
                'rules' => ['max:191'],
            ],
            self::derivedColumn('pipeline_status', 'projects.columns.pipeline_status', sortable: true),
            self::derivedColumn('business_function', 'projects.columns.business_function'),
            self::derivedColumn('country', 'projects.columns.country'),
            self::derivedColumn('state', 'projects.columns.state'),
            self::derivedColumn('province', 'projects.columns.province'),
            self::derivedColumn('city', 'projects.columns.city'),
            [
                'id' => 'geo_scope',
                'label' => 'projects.columns.geo_scope',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            self::derivedColumn('product_category', 'projects.columns.product_category'),
            self::derivedColumn('partner', 'projects.columns.partner'),
            self::displayOnlyColumn('operational_site', 'projects.columns.operational_site'),
            [
                'id' => 'start_date',
                'label' => 'projects.columns.start_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                // Inline cell-editing (spec 0053): real, fillable, mandatory
                // (ProjectsAuthorization) column — never nullable inline.
                'editable' => true,
            ],
            [
                'id' => 'end_date',
                'label' => 'projects.columns.end_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                // Inline cell-editing (spec 0053): real, fillable, nullable column.
                'editable' => true,
                'nullable' => true,
            ],
            [
                'id' => 'total_budget',
                'label' => 'projects.columns.total_budget',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
                // Inline cell-editing (spec 0053): real, fillable, nullable
                // column; mirrors UpdateProjectRequest's own bound.
                'editable' => true,
                'nullable' => true,
                'rules' => ['min:0'],
            ],
            [
                'id' => 'target_lead',
                'label' => 'projects.columns.target_lead',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
                // Inline cell-editing (spec 0053): real, fillable, nullable
                // column; mirrors UpdateProjectRequest's own bound.
                'editable' => true,
                'nullable' => true,
                'rules' => ['min:0'],
            ],
            [
                'id' => 'created_at',
                'label' => 'projects.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
        ];
    }

    /**
     * A DERIVED (related-row-name) column declaration: filterable via the
     * `set` widget, only sortable when explicitly requested (spec 0023: just
     * `pipeline_status`).
     *
     * @return array<string, mixed>
     */
    private static function derivedColumn(string $id, string $label, bool $sortable = false): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => $sortable,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * A COMPUTED, DISPLAY-ONLY column: no real column/relation of its own to
     * sort or filter on, mirroring CampaignColumnCatalog's own helper.
     *
     * @return array<string, mixed>
     */
    private static function displayOnlyColumn(string $id, string $label): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => false,
            'filterable' => false,
        ];
    }

    /**
     * Only FILTERABLE columns get a filter declaration — `geo_scope`
     * (display-only, spec 0027) has no `filterType` to declare.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        $filterable = array_filter(
            self::columns(),
            static fn (array $column): bool => ($column['filterable'] ?? false) === true,
        );

        return array_values(array_map(
            static fn (array $column): array => [
                'columnId' => $column['id'],
                'type' => $column['filterType'],
            ],
            $filterable,
        ));
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
                'permission' => 'projects.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'projects.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'projects.delete',
            ],
            [
                'key' => 'duplicate',
                'label' => 'actions.duplicate',
                'icon' => 'copy',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'projects.create',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'projects.viewActivity',
            ],
        ];
    }
}

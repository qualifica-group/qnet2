<?php

namespace App\Tables\BusinessFunctions;

/**
 * Declarative column/filter/action catalogue for the `business-functions`
 * domain (spec 0010, spec 0010 REV).
 *
 * Extracted out of BusinessFunctionsTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic), mirroring UserColumnCatalog.
 *
 * `manager`, `parent`, `users` and `operational_sites` have no real DB column
 * of their own (manager/parent are relation names, users/operational_sites a
 * belongsToMany) — all four are DERIVED, handled by
 * BusinessFunctionsTableDefinition's applyDerivedFilter/applyDerivedSort/
 * distinctValues (parent/operational_sites delegated to
 * BusinessFunctionParentColumn/BusinessFunctionOperationalSitesColumn).
 * `is_business_unit`/`is_business_service` ARE real columns, so the generic
 * engine handles their `set` filter and distinct values.
 */
final class BusinessFunctionColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'businessFunctions.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'is_business_unit',
                'label' => 'businessFunctions.columns.is_business_unit',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'is_business_service',
                'label' => 'businessFunctions.columns.is_business_service',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Manager's name, derived from the manager() relation. Rendered
                // as an avatar + tooltip on the frontend; sorted via a
                // correlated subquery, filtered via whereHas (both in the
                // definition).
                'id' => 'manager',
                'label' => 'businessFunctions.columns.manager',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // The parent function's name, derived from the self-referencing
                // parent() relation (spec 0010 REV). Sorted via a correlated
                // subquery, filtered via whereHas (BusinessFunctionParentColumn).
                // Root functions (no parent) surface as null/empty.
                'id' => 'parent',
                'label' => 'businessFunctions.columns.parent',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Associated users' names, derived from the users()
                // belongsToMany. Rendered as a stack of avatars. Not sortable
                // (a to-many value has no single sort key); filterable via
                // whereHas.
                'id' => 'users',
                'label' => 'businessFunctions.columns.users',
                'type' => 'tags',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Associated operational sites (spec 0010 REV), derived from
                // the operationalSites() belongsToMany. Rendered as a stack of
                // tags (line1 - city). Not sortable (a to-many value has no
                // single sort key); filterable via whereHas
                // (BusinessFunctionOperationalSitesColumn, matched on the raw
                // primary-address line1).
                'id' => 'operational_sites',
                'label' => 'businessFunctions.columns.operational_sites',
                'type' => 'tags',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'created_at',
                'label' => 'businessFunctions.columns.created_at',
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
            ['columnId' => 'is_business_unit', 'type' => 'set'],
            ['columnId' => 'is_business_service', 'type' => 'set'],
            ['columnId' => 'manager', 'type' => 'set'],
            ['columnId' => 'parent', 'type' => 'set'],
            ['columnId' => 'users', 'type' => 'set'],
            ['columnId' => 'operational_sites', 'type' => 'set'],
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
                'permission' => 'business-functions.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'business-functions.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'business-functions.viewActivity',
            ],
        ];
    }
}

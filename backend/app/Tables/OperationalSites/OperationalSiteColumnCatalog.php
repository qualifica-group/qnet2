<?php

namespace App\Tables\OperationalSites;

/**
 * Declarative column/filter/action catalogue for the `operational-sites`
 * domain (spec 0011).
 *
 * Every column but `created_at` is DERIVED from the site's primary address
 * (no real DB column: the site table carries only id/timestamps) — resolved
 * by the OperationalSiteGeoColumns collaborator. Unlike Users/Companies,
 * where the geo columns default hidden, every column here is `visible` by
 * default: the site IS its address (grid identity = comune + via).
 */
final class OperationalSiteColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'id',
                'label' => 'operationalSites.columns.id',
                'type' => 'number',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                // The site's own label (a REAL column on operational_sites,
                // unlike every geo/address column here). The legacy import
                // fills it from the source `comune` string; sort/filter/search
                // run through the generic engine (real column, no derived hook).
                'id' => 'alias',
                'label' => 'operationalSites.columns.alias',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'hasFilterValues' => false,
                'searchable' => true,
            ],
            [
                // Comune, the name of the primary address' City. Set filter
                // with backend-resolved options; global quick-search spans it
                // (spec 0009/0011, via applyDerivedSearch).
                'id' => 'city',
                'label' => 'operationalSites.columns.city',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'searchable' => true,
            ],
            [
                // Via (line1). COMPUTED (no real DB column) and
                // conditions-only, like Users' `primary_address`: a street
                // line has no clean discrete list, so hasFilterValues=false —
                // no Set/checklist, no /values call. Also quick-searchable.
                'id' => 'street',
                'label' => 'operationalSites.columns.street',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'hasFilterValues' => false,
                'searchable' => true,
            ],
            [
                // CAP. Same COMPUTED/conditions-only shape as `street`, not
                // part of the global quick-search (spec 0011 contract).
                'id' => 'postal_code',
                'label' => 'operationalSites.columns.postal_code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'hasFilterValues' => false,
            ],
            [
                'id' => 'province',
                'label' => 'operationalSites.columns.province',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Regione, the name of the primary address' State (a State IS
                // a region in this geo hierarchy).
                'id' => 'region',
                'label' => 'operationalSites.columns.region',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Spec 0135: real column; an inactive site is no longer
                // offered by the for-select pickers.
                'id' => 'is_active',
                'label' => 'operationalSites.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'created_at',
                'label' => 'operationalSites.columns.created_at',
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
            // Real column: conditions-only text filter (no discrete value list).
            ['columnId' => 'alias', 'type' => 'text'],
            // Geo set filters: options resolved dynamically in optionsFor().
            ['columnId' => 'city', 'type' => 'set'],
            ['columnId' => 'street', 'type' => 'text'],
            ['columnId' => 'postal_code', 'type' => 'text'],
            ['columnId' => 'province', 'type' => 'set'],
            ['columnId' => 'region', 'type' => 'set'],
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
                'permission' => 'operational-sites.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'operational-sites.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'operational-sites.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'operational-sites.viewActivity',
            ],
        ];
    }
}

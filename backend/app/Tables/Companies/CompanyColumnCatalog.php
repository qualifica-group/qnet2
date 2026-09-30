<?php

namespace App\Tables\Companies;

/**
 * Declarative column/filter/action catalogue for the `companies` domain
 * (spec 0010).
 *
 * Extracted out of CompaniesTableDefinition (file-size split, engineering.md
 * §6): pure data (no logic), mirroring UserColumnCatalog. The 5 geo/postal
 * columns (city/province/region/postal_code/country) have no real DB column
 * of their own — derived from the company's primary address, handled by
 * CompanyAddressColumns.
 */
final class CompanyColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'id',
                'label' => 'companies.columns.id',
                'type' => 'number',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'denomination',
                'label' => 'companies.columns.denomination',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'vat_number',
                'label' => 'companies.columns.vat_number',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                // Geo name from the primary address. Hidden by default; set
                // filter with backend-resolved options (distinct names in
                // use). Sorted via a correlated subquery.
                'id' => 'city',
                'label' => 'companies.columns.city',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'province',
                'label' => 'companies.columns.province',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'region',
                'label' => 'companies.columns.region',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Free-text postal code of the primary address. COMPUTED (no
                // real DB column) and conditions-only, like `primary_address`
                // (spec 0005): a single code has no clean discrete list worth
                // an Excel-like Set Filter, so hasFilterValues=false.
                'id' => 'postal_code',
                'label' => 'companies.columns.postal_code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'hasFilterValues' => false,
            ],
            [
                'id' => 'country',
                'label' => 'companies.columns.country',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'created_at',
                'label' => 'companies.columns.created_at',
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
            ['columnId' => 'denomination', 'type' => 'text'],
            ['columnId' => 'vat_number', 'type' => 'text'],
            // Geo set filters: options resolved dynamically in optionsFor().
            ['columnId' => 'city', 'type' => 'set'],
            ['columnId' => 'province', 'type' => 'set'],
            ['columnId' => 'region', 'type' => 'set'],
            ['columnId' => 'postal_code', 'type' => 'text'],
            ['columnId' => 'country', 'type' => 'set'],
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
                'permission' => 'companies.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'companies.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'companies.viewActivity',
            ],
        ];
    }
}

<?php

namespace App\Tables\CompanySites;

/**
 * Declarative column/filter/action catalogue for the `company-sites` domain
 * (spec 0020).
 *
 * Extracted out of CompanySitesTableDefinition (file-size split, engineering.md
 * §6): pure data (no logic), mirroring RegistryColumnCatalog. `primary_contact`
 * is COMPUTED from the card's contacts (shared PrimaryContactColumn), like the
 * Registry grid; the 4 geo/postal columns (city/province/region/postal_code)
 * have no real DB column of their own — derived from the card's primary
 * address, handled by CompanySiteAddressColumns. Email/vat_number/phone are no
 * longer real columns (they live on the card / its contacts), so they are not
 * grid columns of their own — `primary_contact` surfaces the contact channels
 * instead. `logo_url` is a derived data: URI (mirrors UserColumnCatalog's
 * `avatar_url`), display-only. `company` has no real DB column of its own
 * either — it is the owning Company's denomination, derived from the
 * `company_id` FK, handled by CompanySitesTableDefinition (mirrors Referents'
 * `referent_type`).
 */
final class CompanySiteColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                // Logo, embedded inline as a data: URI; the frontend renders it
                // via a custom avatar cell (not sortable nor filterable — it is
                // a derived value, not a real column). Mirrors UserColumnCatalog's
                // avatar_url.
                'id' => 'logo_url',
                'label' => 'companySites.columns.logo',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
                'filterType' => null,
                // Narrow, fixed default: the cell only holds a small logo.
                'width' => 56,
            ],
            [
                'id' => 'id',
                'label' => 'companySites.columns.id',
                'type' => 'number',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                // Exclusive site-level default flag (spec 0020), toggled via
                // POST /company-sites/{id}/set-default. Real column.
                'id' => 'is_default',
                'label' => 'companySites.columns.isDefault',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'name',
                'label' => 'companySites.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                // The owning Company's denomination, derived from the
                // company() belongsTo relation. Sortable via a correlated
                // subquery, set-filterable by name — mirrors Referents'
                // `referent_type`.
                'id' => 'company',
                'label' => 'companySites.columns.company',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // The card's primary contacts (shared PrimaryContactColumn),
                // display-only here: neither sortable nor filterable (mirrors
                // the Registry grid, spec 0020 data contract).
                'id' => 'primary_contact',
                'label' => 'companySites.columns.primaryContact',
                'type' => 'tags',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                // Geo name from the primary address. Set filter with
                // backend-resolved options (distinct names in use). Sorted via
                // a correlated subquery.
                'id' => 'city',
                'label' => 'companySites.columns.city',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'province',
                'label' => 'companySites.columns.province',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'region',
                'label' => 'companySites.columns.region',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Free-text postal code of the primary address. COMPUTED (no
                // real DB column), conditions-only (spec 0005): no clean
                // discrete list worth an Excel-like Set Filter.
                'id' => 'postal_code',
                'label' => 'companySites.columns.postalCode',
                'type' => 'text',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'hasFilterValues' => false,
            ],
            [
                'id' => 'created_at',
                'label' => 'companySites.columns.createdAt',
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
            ['columnId' => 'is_default', 'type' => 'set'],
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'company', 'type' => 'set'],
            // Geo set filters: options resolved dynamically in optionsFor().
            ['columnId' => 'city', 'type' => 'set'],
            ['columnId' => 'province', 'type' => 'set'],
            ['columnId' => 'region', 'type' => 'set'],
            ['columnId' => 'postal_code', 'type' => 'text'],
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
                'permission' => 'company-sites.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'company-sites.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'company-sites.viewActivity',
            ],
        ];
    }
}

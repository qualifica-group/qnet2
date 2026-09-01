<?php

namespace App\Tables\Referents;

/**
 * Declarative column/filter/action catalogue for the `referents` domain
 * (spec 0016).
 *
 * Extracted out of ReferentsTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic), mirroring BusinessFunctionColumnCatalog.
 *
 * `referent_type` has no real DB column of its own (it is the related
 * ReferentType's name) — DERIVED, handled by ReferentsTableDefinition's
 * applyDerivedFilter/applyDerivedSort/distinctValues. `contact_scope` IS a
 * real column, so the generic engine handles its `set` filter, sort and
 * distinct values. `primary_contact` is COMPUTED from the card's contacts (no
 * real column) and behaves IDENTICALLY to the Users column via the shared
 * PrimaryContactColumn: sortable + filterable (text/set), resolved by
 * ReferentsTableDefinition's applyDerivedFilter/applyDerivedSort/distinctValues.
 * `user` (spec 0090, D-3) is likewise DERIVED (the linked user's name via
 * `referents.user_id`), APPENDED after `created_at` — inserting it earlier
 * would shift the columns of layouts users already saved — and resolved by
 * ReferentUserColumn, mirroring BusinessFunctions' own `manager` column.
 */
final class ReferentColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'referents.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                // Referent type's name, derived from the referentType()
                // relation. Sorted via a correlated subquery, filtered via
                // whereHas (both in the definition).
                'id' => 'referent_type',
                'label' => 'referents.columns.referent_type',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'contact_scope',
                'label' => 'referents.columns.contact_scope',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // ALL primary contacts (one per type), IDENTICAL to the Users
                // `primary_contact` column (shared PrimaryContactColumn):
                // rendered as tags (count badge + tooltip), the text filter
                // matches ANY primary contact via whereHas LIKE, sorted by the
                // first primary contact value (MIN) via a correlated subquery,
                // and the Set Filter list resolves from the distinct contact
                // values (hasFilterValues defaults to true).
                'id' => 'primary_contact',
                'label' => 'referents.columns.primary_contact',
                'type' => 'tags',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'created_at',
                'label' => 'referents.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                // The linked user's name (spec 0090, D-3), derived from the
                // user() relation. APPENDED after created_at (D-3): inserting
                // it earlier would shift already-saved column layouts.
                'id' => 'user',
                'label' => 'referents.columns.user',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
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
            ['columnId' => 'referent_type', 'type' => 'set'],
            ['columnId' => 'contact_scope', 'type' => 'set'],
            ['columnId' => 'primary_contact', 'type' => 'text'],
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'user', 'type' => 'set'],
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
                'permission' => 'referents.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'referents.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'referents.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'referents.viewActivity',
            ],
        ];
    }
}

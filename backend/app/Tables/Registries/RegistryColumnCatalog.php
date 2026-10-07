<?php

namespace App\Tables\Registries;

/**
 * Declarative column/filter/action catalogue for the `registries` domain
 * (spec 0020, "Anagrafiche").
 *
 * Extracted out of RegistriesTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic), mirroring ReferentColumnCatalog.
 *
 * `source`/`commercial`/`supervisor`/`reporter` have no real DB column of
 * their own (each is the related row's name) and `managers` is the
 * `registry_user` pivot (to-many, labelled "Operatori") — all DERIVED,
 * handled by RegistryRelationColumns, mirroring the Opportunita' catalogue;
 * `managers` is filterable but not sortable (no single sort key). `is_supplier`/
 * `agreement_status`/`size_class` ARE real columns, so the generic engine
 * handles their `set` filter and sort; only their distinct-values need a
 * definition override (cast-bypassing `toBase()`, mirroring
 * `distinctContactScopes`). `primary_contact` is COMPUTED from the card's
 * contacts (shared PrimaryContactColumn::format(), like Users/Referents) but,
 * unlike those two, is neither sortable nor filterable here (spec 0020 data
 * contract) — display-only.
 */
final class RegistryColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'registries.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                // Source's name, derived from the source() relation. Sorted
                // via a correlated subquery, filtered via whereHas (both in
                // the definition).
                'id' => 'source',
                'label' => 'registries.columns.source',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'is_supplier',
                'label' => 'registries.columns.is_supplier',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'agreement_status',
                'label' => 'registries.columns.agreement_status',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'size_class',
                'label' => 'registries.columns.size_class',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // The card's primary contacts (shared PrimaryContactColumn),
                // display-only here: neither sortable nor filterable (spec
                // 0020 data contract), unlike the identical Users/Referents
                // column.
                'id' => 'primary_contact',
                'label' => 'registries.columns.primary_contact',
                'type' => 'tags',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            self::relationColumn('commercial'),
            self::relationColumn('supervisor'),
            self::relationColumn('reporter'),
            [
                // Account managers (registry_user pivot, to-many), rendered as
                // an avatar stack under the "Operatori" label.
                'id' => 'managers',
                'label' => 'registries.columns.managers',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'created_at',
                'label' => 'registries.columns.created_at',
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
            ['columnId' => 'source', 'type' => 'set'],
            ['columnId' => 'is_supplier', 'type' => 'set'],
            ['columnId' => 'agreement_status', 'type' => 'set'],
            ['columnId' => 'size_class', 'type' => 'set'],
            ['columnId' => 'commercial', 'type' => 'set'],
            ['columnId' => 'supervisor', 'type' => 'set'],
            ['columnId' => 'reporter', 'type' => 'set'],
            ['columnId' => 'managers', 'type' => 'set'],
            ['columnId' => 'created_at', 'type' => 'date'],
        ];
    }

    /**
     * A simple relation-name derived column (own FK on the registry), sorted
     * via a correlated subquery and filtered via whereHas by name.
     *
     * @return array<string, mixed>
     */
    private static function relationColumn(string $id): array
    {
        return [
            'id' => $id,
            'label' => "registries.columns.{$id}",
            'type' => 'text',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'set',
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
                'permission' => 'registries.view',
            ],
            [
                'key' => 'documents',
                'label' => 'actions.documents',
                'icon' => 'paperclip',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'registries.viewDocuments',
                'count_field' => 'documents_count',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'registries.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'registries.viewActivity',
            ],
        ];
    }
}

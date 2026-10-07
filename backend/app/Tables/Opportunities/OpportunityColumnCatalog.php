<?php

namespace App\Tables\Opportunities;

use App\Tables\Shared\ProductsOfInterestColumn;

/**
 * Declarative column/filter/action catalogue for the `opportunities` domain
 * (spec 0040). Extracted out of OpportunitiesTableDefinition (file-size
 * split, engineering.md §6): pure data (no logic).
 *
 * `name`/`estimated_value`/`success_probability`/`start_date`/
 * `expected_close_date`/`created_at` are real DB columns handled entirely by
 * the generic engine. `registry`/`referent`/`commercial`/`supervisor`/
 * `source` are STANDARD relation-name derived columns (own FK on the
 * opportunity), resolved by OpportunitiesTableDefinition — all 5 sortable (a
 * correlated subquery), mirroring LeadColumnCatalog. Amendment rev.3:
 * `product_category`/`business_function` are AGGREGATED to-many columns
 * (via `productLines`) — filterable (`set`, `whereHas`) but NOT sortable (no
 * single related row to order by).
 *
 * User directive 2026-07-23: `products_of_interest` is a PIVOT
 * (belongsToMany) to-many column — filterable (`set`, `whereHas`), NOT
 * sortable, and inline-editable through the shared `multiselect` editor; its
 * whole declaration lives in App\Tables\Shared\ProductsOfInterestColumn,
 * shared with the request-management catalogue.
 *
 * Spec 0056: `operational_site` is a SPECIALLY-derived column (the site has
 * no own name — sort/filter/distinct pass through its primary address
 * `line1`, delegated to the shared App\Tables\Shared\OperationalSiteColumn,
 * never the generic name-based DERIVED_RELATIONS mechanism) — sortable AND
 * filterable (set), but NOT `editable`: the form does not offer it either
 * (spec 0198 D-2).
 *
 * Spec 0206: every column the form edits as a single field is
 * inline-editable through OpportunityCellWriter (UpdateOpportunityRequest +
 * OpportunityService) — `name` included (RETTIFICA 0171: the
 * OpportunityNameWriter still decides automatic vs manual). `status`,
 * `business_function` (derived from the product lines) and `created_at`
 * stay read-only.
 */
final class OpportunityColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'opportunities.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
                // Spec 0206, D-8: written by OpportunityNameWriter through the
                // service (blank = back to the automatic title).
                'editable' => true,
                'nullable' => true,
            ],
            [...self::derivedColumn('registry', 'opportunities.columns.registry'), ...self::editableRelation('registries', 'registry_id', nullable: false)],
            [
                ...self::derivedColumn('referent', 'opportunities.columns.referent'),
                ...self::editableRelation('referents', 'referent_id'),
                // As in the form, only the anagrafica's own referents (BR-4).
                'relation' => ['resource' => 'referents', 'scope' => ['registry_id' => 'registry'], 'lockScope' => true],
            ],
            [...self::derivedColumn('commercial', 'opportunities.columns.commercial'), ...self::editableRelation('referents', 'commercial_id')],
            [...self::derivedColumn('supervisor', 'opportunities.columns.supervisor'), ...self::editableRelation('users', 'supervisor_id')],
            // Account managers (opportunity_user pivot, to-many), rendered as an
            // avatar stack. Not sortable (a to-many value has no single sort
            // key); filterable via whereHas on the manager's name.
            [
                'id' => 'managers',
                'label' => 'opportunities.columns.managers',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
                'editable' => true,
                'editor' => 'multiselect',
                'relation' => ['resource' => 'users'],
                'editableField' => 'manager_slots',
            ],
            [...self::derivedColumn('source', 'opportunities.columns.source'), ...self::editableRelation('sources', 'source_id')],
            self::derivedColumn('operational_site', 'opportunities.columns.operationalSite'),
            OpportunityStatusColumn::declaration('opportunities.columns.status'),
            [
                ...self::aggregatedColumn('product_category', 'opportunities.columns.productCategory'),
                // Spec 0206, D-9: the same two-step editor as Gestione Richieste.
                'editable' => true,
                'editor' => 'product_lines',
                'editableField' => 'product_lines',
            ],
            self::aggregatedColumn('business_function', 'opportunities.columns.businessFunction'),
            ProductsOfInterestColumn::declaration('opportunities.columns.productsOfInterest'),
            [
                'id' => 'estimated_value',
                'label' => 'opportunities.columns.estimatedValue',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
                'editable' => true,
                'nullable' => true,
            ],
            [
                'id' => 'success_probability',
                'label' => 'opportunities.columns.successProbability',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
                'editable' => true,
                'nullable' => true,
            ],
            [
                'id' => 'start_date',
                'label' => 'opportunities.columns.startDate',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                'editable' => true,
                'nullable' => true,
            ],
            [
                'id' => 'expected_close_date',
                'label' => 'opportunities.columns.expectedCloseDate',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                'editable' => true,
                'nullable' => true,
            ],
            [
                'id' => 'created_at',
                'label' => 'opportunities.columns.createdAt',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
        ];
    }

    /**
     * Spec 0206: the inline-editing keys of a single-id relation column —
     * `editableField` is the form's own key, written through
     * OpportunityCellWriter.
     *
     * @return array<string, mixed>
     */
    private static function editableRelation(string $resource, string $editableField, bool $nullable = true): array
    {
        return [
            'editable' => true,
            'relation' => ['resource' => $resource],
            'editableField' => $editableField,
            'nullable' => $nullable,
        ];
    }

    /**
     * A DERIVED (related-row-name) column declaration: filterable via the
     * `set` widget and sortable (every one of the 5 relational columns here
     * has a correlated-subquery sort), mirroring LeadColumnCatalog.
     *
     * @return array<string, mixed>
     */
    private static function derivedColumn(string $id, string $label): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * A to-many AGGREGATED (via `productLines`) column declaration: filterable
     * via `set` (whereHas on the related row's name) but never sortable — no
     * single related row to order by (amendment rev.3).
     *
     * @return array<string, mixed>
     */
    private static function aggregatedColumn(string $id, string $label): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => false,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return array_map(
            static fn (array $column): array => [
                'columnId' => $column['id'],
                'type' => $column['filterType'],
            ],
            self::columns(),
        );
    }

    /**
     * Same layout as the Gestione Richieste catalog (user directive
     * 2026-08-05): `view`, `documents`, `notes` inline (INLINE_ACTION_LIMIT =
     * 3), `delete` and `activity` in the overflow menu. `edit` is NOT a row
     * action here — editing starts from the record's own detail surface,
     * whose Edit button is gated by the same `opportunities.update` the
     * update endpoint re-checks. `notes` is gated by `request-management.view`
     * because the thread is registered under the `request-management`
     * entity_type (config/notes.php → RequestManagementNotable): the note
     * endpoints would 403 an actor holding only `opportunities.*`.
     *
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
                'permission' => 'opportunities.view',
            ],
            [
                'key' => 'documents',
                'label' => 'actions.documents',
                'icon' => 'paperclip',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'opportunities.viewDocuments',
                'count_field' => 'documents_count',
            ],
            [
                'key' => 'notes',
                'label' => 'actions.notes',
                'icon' => 'messages-square',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'request-management.view',
                'count_field' => 'notes_count',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'opportunities.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'opportunities.viewActivity',
            ],
        ];
    }
}

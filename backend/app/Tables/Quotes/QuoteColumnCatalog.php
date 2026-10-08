<?php

namespace App\Tables\Quotes;

/**
 * Declarative column/filter/action catalogue for the `quotes` domain (spec
 * 0065, MT-05). Extracted out of QuotesTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic).
 *
 * `code`/`title`/`created_at` are real DB columns handled entirely by the
 * generic engine. `revenue_net`/`cost_net`/`margin_net` are ALSO real DB
 * columns (the persisted header aggregates, D-9) — sortable/filterable by
 * the generic engine, no derived-column handling needed. `opportunity`/
 * `quote_workflow_status`/`commercial`/`reporter`/`supervisor` are STANDARD
 * relation-name derived columns (own FK on the quote), resolved by
 * QuotesTableDefinition/QuoteRelationColumns — all 5 sortable (a correlated
 * subquery), mirroring OpportunityColumnCatalog. `company`/`company_site`
 * (user directive 2026-07-30) join that set — `company` labelled by its
 * `denomination`, see QuoteRelationColumns — while `operational_site` is a
 * SPECIALLY-derived column (the site has no label column at all: it is
 * identified by its primary address), delegated by QuotesTableDefinition to
 * the shared OperationalSiteColumn exactly as on Opportunities.
 *
 * `next_callback_at` ("Prossimo richiamo", user directive 2026-09-04) is a
 * real `quotes` column too — moved off the Opportunity with the planning it
 * represents — so the generic engine sorts and date-filters it with no
 * derived handling. Read-only here: the Offerta's callback is planned from
 * Gestione Richieste, the module that owns the write path (the
 * reminder-marker invariant lives behind RequestManagementService).
 *
 * `alert` (spec 0102, D-4/AC-030..036) is specially derived by
 * QuotesTableDefinition: `missing_offer_lines` when the Offerta has zero
 * REVENUE lines, else `null` — mirrors ContractColumnCatalog's own `alert`
 * entry (badge, `set`-filterable, never sortable — no single value to order
 * a static two-state enumeration by).
 *
 * Spec 0206: every column the form edits as a single field is
 * inline-editable through QuoteCellWriter (UpdateQuoteRequest +
 * QuoteService); `code`, `opportunity`, the aggregates, `created_at`,
 * `next_callback_at` and `alert` stay read-only. `managers` is edited as a
 * list of people, mapped onto the positional `manager_slots`.
 */
final class QuoteColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'code',
                'label' => 'quotes.columns.code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'title',
                'label' => 'quotes.columns.title',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
                // Spec 0206: blank = back to the automatic title (QuoteTitleWriter).
                'editable' => true,
                'nullable' => true,
            ],
            self::derivedColumn('opportunity', 'quotes.columns.opportunity'),
            [
                ...self::derivedColumn('quote_workflow_status', 'quotes.columns.quoteWorkflowStatus'),
                // Spec 0206, D-6: same select + transition note as Gestione Richieste.
                'editable' => true,
                'editor' => 'select',
                'editableField' => 'quote_workflow_status_id',
                'nullable' => false,
                'notable' => true,
            ],
            [...self::derivedColumn('commercial', 'quotes.columns.commercial'), ...self::editableRelation('referents', 'commercial_id')],
            [...self::derivedColumn('reporter', 'quotes.columns.reporter'), ...self::editableRelation('referents', 'reporter_id')],
            [...self::derivedColumn('supervisor', 'quotes.columns.supervisor'), ...self::editableRelation('users', 'supervisor_id')],
            // "Gestori Account" (spec 0087, D-1/T-10) — mirrors
            // OpportunityColumnCatalog's own `managers` entry: a to-many
            // rendered as an avatar stack, not sortable (no single sort key),
            // filterable via whereHas on the manager's name.
            // Sits beside the Supervisore (user directive 2026-08-31): the two
            // read together as "chi segue questa offerta", and appending it
            // last buried it off the right edge of the grid. Deliberately
            // breaks the append-only convention (spec 0001) — safe here because
            // the stored preference delta is keyed by column ID, so a user's
            // saved layout keeps its own order and only DEFAULT layouts move.
            [
                'id' => 'managers',
                'label' => 'quotes.columns.managers',
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
            [
                'id' => 'revenue_net',
                'label' => 'quotes.columns.revenueNet',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'cost_net',
                'label' => 'quotes.columns.costNet',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'margin_net',
                'label' => 'quotes.columns.marginNet',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'created_at',
                'label' => 'quotes.columns.createdAt',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            // Appended LAST on purpose (user directive 2026-07-30): a column
            // added in the middle would shift every user's persisted column
            // layout (spec 0001); appended, it just shows up at the end.
            [...self::derivedColumn('company', 'quotes.columns.company'), ...self::editableRelation('companies', 'company_id')],
            [...self::derivedColumn('company_site', 'quotes.columns.companySite'), ...self::editableRelation('company-sites', 'company_site_id', ['company_id' => 'company'])],
            [...self::derivedColumn('operational_site', 'quotes.columns.operationalSite'), ...self::editableRelation('operational-sites', 'operational_site_id')],
            // Appended LAST for the same reason as the three above (spec
            // 0001): a column inserted mid-catalogue would shift every user's
            // persisted layout.
            [
                'id' => 'next_callback_at',
                'label' => 'quotes.columns.nextCallbackAt',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            // Appended LAST for the same reason (spec 0001, AC-035): `alert`
            // arrived after every other column here.
            [
                'id' => 'alert',
                'label' => 'quotes.columns.alert',
                'type' => 'badge',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
                'options' => ['missing_offer_lines'],
            ],
        ];
    }

    /**
     * Spec 0206: the inline-editing keys of a single-id relation column —
     * `editableField` is the form's own key, written through QuoteCellWriter;
     * `$scope` narrows the picker by another cell of the row (the form's own
     * dependent picker).
     *
     * @param  array<string, string>  $scope
     * @return array<string, mixed>
     */
    private static function editableRelation(string $resource, string $editableField, array $scope = []): array
    {
        return [
            'editable' => true,
            'relation' => $scope === [] ? ['resource' => $resource] : ['resource' => $resource, 'scope' => $scope],
            'editableField' => $editableField,
            'nullable' => true,
        ];
    }

    /**
     * A DERIVED (related-row-label) column declaration: filterable via the
     * `set` widget and sortable (every relational column here has a
     * correlated-subquery sort).
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
     * L'ORDINE E' PORTANTE, non estetico: il frontend rende inline le prime
     * INLINE_ACTION_LIMIT (3, `row-actions.tsx`) azioni permesse alla riga e
     * manda tutte le altre nel menu di overflow. Le tre in testa —
     * `view`, `generate_document`, `notes` (direttiva utente 2026-08-06) —
     * sono quelle a un click; `edit`/`delete`/`activity` seguono nei tre
     * puntini. Chi inserisce un'azione in mezzo cambia cio' che l'utente vede
     * inline: si accoda in fondo salvo richiesta esplicita.
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
                'permission' => 'quotes.view',
            ],
            // spec 0070: generates the quote's `.docx` in-request (D-2), a
            // pure read — gated by the same `quotes.view` as the `view`
            // action above, not `update`.
            [
                'key' => 'generate_document',
                'label' => 'actions.generatePdf',
                'icon' => 'file-text',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'quotes.view',
            ],
            // Spec 0085: le note dell'Offerta vivono sul thread dell'Opportunita'
            // padre, filtrate su `quote_id` — quindi il gate e' quello del modulo
            // ospite (`request-management.view`, come sulla griglia Opportunita'),
            // mai una permission `quotes.*`. Il `count_field` conta le note di
            // QUESTA offerta (emendamento 2026-08-06): il badge deve descrivere
            // cio' che il dialog mostra, cioe' il thread gia' filtrato.
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
                'permission' => 'quotes.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'quotes.viewActivity',
            ],
        ];
    }
}

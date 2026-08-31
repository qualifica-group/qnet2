<?php

declare(strict_types=1);

namespace App\Tables\Contracts;

/**
 * Declarative column/filter/action catalogue for the `contracts` domain
 * (spec 0072, MT-04). Extracted out of ContractsTableDefinition (file-size
 * split, engineering.md §6): pure data (no logic).
 *
 * D-1: `contracts` owns no code/title/client/amount column of its own — every
 * one of `code`/`title`/`registry`/`opportunity`/`commercial`/`reporter`/
 * `supervisor`/`quote_date`/`revenue_net`/`revenue_vat` is DERIVED through the
 * 1-1 `quote` relation (resolved by ContractRelationColumns); `contract_status`
 * is the one single-hop relation, a real FK on `contracts` itself.
 * `accepted_at`/`validated_at`/`renewal_date`/`expiry_date`/`terminated_at` are
 * real `contracts` columns, handled entirely by the generic engine.
 *
 * `code`/`title`/`quote_date`/`revenue_net`/`revenue_vat` declare
 * `hasFilterValues: false`: they have no real DB column on `contracts` to
 * `SELECT DISTINCT` on (the generic engine's fallback would fail), and — like
 * `postal_code`/`street` elsewhere — carry no clean discrete list anyway.
 *
 * `revenue_gross` (D-9-style: net + vat, never persisted) is sortable via a
 * constant correlated-subquery expression (ContractsTableDefinition,
 * backend.md §8's sole permitted raw escape hatch) but deliberately NOT
 * filterable: a WHERE on a computed sum would need the same raw arithmetic
 * inside a filter predicate for a feature no AC requires — out of scope,
 * kept minimal (engineering.md §1.3).
 *
 * `alert` (BR-6/D-4) is calculated at read time by ContractAlertResolver,
 * never a real column: `set`-filterable over its 2 possible values, never
 * sortable (a derived qualitative flag, not a meaningful ordering axis).
 *
 * `managers` (user directive 2026-08-31) is the offer's G.A. team, a to-many
 * through `quote.managers`: `set`-filterable on the manager's name, never
 * sortable (no single related row to order by).
 *
 * `commercial`/`supervisor`/`managers` ship HIDDEN by default (same
 * directive): available in the column picker, just not in the opening
 * layout.
 */
final class ContractColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'code',
                'label' => 'contracts.columns.code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
                'hasFilterValues' => false,
            ],
            [
                'id' => 'title',
                'label' => 'contracts.columns.title',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
                'hasFilterValues' => false,
            ],
            self::relationColumn('registry', 'contracts.columns.registry'),
            self::relationColumn('opportunity', 'contracts.columns.opportunity'),
            self::relationColumn('commercial', 'contracts.columns.commercial', visible: false),
            self::relationColumn('reporter', 'contracts.columns.reporter'),
            self::relationColumn('supervisor', 'contracts.columns.supervisor', visible: false),
            // "Gestori Account" (user directive 2026-08-31) — the same
            // avatar-stack column the Offerte grid carries (spec 0087
            // D-1/T-10), read through the 1-1 `quote` like every other
            // display column of this domain (D-1): a to-many, so never
            // sortable, `set`-filterable on the manager's name.
            // Beside the Supervisore, the two reading together as "chi segue
            // questo contratto" — same placement directive the Offerte grid
            // got, and same deliberate break of spec 0001's append-only
            // convention (the stored preference delta is keyed by column ID,
            // so only DEFAULT layouts move).
            [
                'id' => 'managers',
                'label' => 'contracts.columns.managers',
                'type' => 'text',
                'visible' => false,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            self::relationColumn('contract_status', 'contracts.columns.contractStatus'),
            [
                'id' => 'quote_date',
                'label' => 'contracts.columns.quoteDate',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                'hasFilterValues' => false,
            ],
            [
                'id' => 'accepted_at',
                'label' => 'contracts.columns.acceptedAt',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'validated_at',
                'label' => 'contracts.columns.validatedAt',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'renewal_date',
                'label' => 'contracts.columns.renewalDate',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'expiry_date',
                'label' => 'contracts.columns.expiryDate',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'terminated_at',
                'label' => 'contracts.columns.terminatedAt',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'revenue_net',
                'label' => 'contracts.columns.revenueNet',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
                'hasFilterValues' => false,
            ],
            [
                'id' => 'revenue_vat',
                'label' => 'contracts.columns.revenueVat',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
                'hasFilterValues' => false,
            ],
            [
                'id' => 'revenue_gross',
                'label' => 'contracts.columns.revenueGross',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
            [
                'id' => 'alert',
                'label' => 'contracts.columns.alert',
                'type' => 'badge',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
                'options' => ['expiring', 'renewal_due'],
            ],
        ];
    }

    /**
     * A relation-derived (related-row-name) `set` column: filterable via the
     * Excel-like Set widget and sortable (a correlated subquery, resolved by
     * ContractRelationColumns).
     *
     * `$visible` false leaves the column out of the DEFAULT layout while
     * keeping it fully available in the column picker (spec 0001): the grid
     * opens on the commercial data that identifies a contract, and the
     * people columns are opt-in (user directive 2026-08-31).
     *
     * @return array<string, mixed>
     */
    private static function relationColumn(string $id, string $label, bool $visible = true): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => $visible,
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
        return [
            ['columnId' => 'code', 'type' => 'text'],
            ['columnId' => 'title', 'type' => 'text'],
            ['columnId' => 'registry', 'type' => 'set'],
            ['columnId' => 'opportunity', 'type' => 'set'],
            ['columnId' => 'commercial', 'type' => 'set'],
            ['columnId' => 'reporter', 'type' => 'set'],
            ['columnId' => 'supervisor', 'type' => 'set'],
            ['columnId' => 'managers', 'type' => 'set'],
            ['columnId' => 'contract_status', 'type' => 'set'],
            ['columnId' => 'quote_date', 'type' => 'date'],
            ['columnId' => 'accepted_at', 'type' => 'date'],
            ['columnId' => 'validated_at', 'type' => 'date'],
            ['columnId' => 'renewal_date', 'type' => 'date'],
            ['columnId' => 'expiry_date', 'type' => 'date'],
            ['columnId' => 'terminated_at', 'type' => 'date'],
            ['columnId' => 'revenue_net', 'type' => 'number'],
            ['columnId' => 'revenue_vat', 'type' => 'number'],
            ['columnId' => 'alert', 'type' => 'set', 'options' => ['expiring', 'renewal_due']],
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
                'permission' => 'contracts.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'contracts.update',
            ],
            [
                'key' => 'validate',
                'label' => 'contracts.actions.validate',
                'icon' => 'check-circle',
                // Chiusura POSITIVA del contratto: `success` e' la controparte
                // di `danger` (direttiva utente 2026-08-31) — il frontend ne
                // deriva un unico colore, in griglia e nella barra azioni della
                // scheda (features/table/action-tone.ts).
                'type' => 'success',
                'confirm' => true,
                'permission' => 'contracts.validate',
            ],
            [
                'key' => 'schedule',
                'label' => 'contracts.actions.schedule',
                'icon' => 'calendar-clock',
                'type' => 'action',
                'confirm' => true,
                'permission' => 'contracts.schedule',
            ],
            [
                'key' => 'change_status',
                'label' => 'contracts.actions.changeStatus',
                'icon' => 'shuffle',
                'type' => 'action',
                'confirm' => true,
                'permission' => 'contracts.changeStatus',
            ],
            [
                'key' => 'terminate',
                'label' => 'contracts.actions.terminate',
                'icon' => 'ban',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'contracts.terminate',
            ],
            [
                'key' => 'reactivate',
                'label' => 'contracts.actions.reactivate',
                'icon' => 'rotate-ccw',
                // Riporta un contratto chiuso in lavorazione: non e' un esito,
                // ne' positivo ne' negativo, quindi tinta neutra e non verde
                // (direttiva utente 2026-08-31 rev.3).
                'type' => 'action',
                'confirm' => true,
                'permission' => 'contracts.reactivate',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'contracts.viewActivity',
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Support\ManagerPositions;

/**
 * The Gestori Account SLOT columns of the `request-management` grid: the GA2
 * "Operatore" (spec 0055/0086/0087/0097) and the GA1 (direttiva utente
 * 2026-09-07, moved from position 3 onto position 1 by the direttiva utente
 * 2026-09-08). Each is an in-cell `users/for-select` picker over ONE position
 * of the offer's `quote_user` team, and each carries the same three
 * peculiarities no other column of this domain has:
 *  - its WRITE lands on a pivot row, never on a column of the row's own
 *    table, so it travels through RequestManagementService::updateWork();
 *  - its HEADER is rewritten per category tab, from that category's effective
 *    `manager_labels` (spec 0080, extended to GA1) — POSITIONS below is the
 *    map RequestManagementScopedTableDefinition relabels through, so the
 *    "which position names which column" answer lives in ONE place;
 *  - it is neither sortable nor filterable (AC-011: this domain never ordered
 *    or filtered on the team, and the migrations that moved the underlying
 *    model deliberately left that behaviour untouched).
 *
 * Split out of RequestColumnCatalog, which had reached the file-size ceiling
 * (engineering.md §6), on that cohesion — the same reason the client
 * anagraphic columns already live in RequestClientColumns.
 */
final class RequestManagerColumns
{
    /** The GA2 "Operatore" column id — never changes, only its `label` does (spec 0080). */
    public const string OPERATOR_COLUMN_ID = 'operator_ga2';

    /** The GA1 column id (direttiva utente 2026-09-07). */
    public const string GA1_COLUMN_ID = 'manager_ga1';

    /**
     * The `manager_labels` position each column is named after: the single
     * source of the per-tab relabel (spec 0080, AC-045).
     *
     * @var array<int, string>
     */
    public const array POSITIONS = [
        ManagerPositions::OPERATOR => self::OPERATOR_COLUMN_ID,
        ManagerPositions::GA1 => self::GA1_COLUMN_ID,
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                // Inline cell-editing (spec 0055, D-6): the same relation
                // column LeadColumnCatalog already declares for its operator —
                // an async `/for-select` picker over `users`. Spec 0087, D-9:
                // the value writes the offer's OWN GA2 slot, denormalized onto
                // `quotes.operator_id`. Nullable: clearing the cell un-assigns
                // the request.
                //
                // `editableField` (spec 0097, D-3a): `manager_slots`. The
                // key is BOTH the field-permission key and the key
                // TableCellUpdateService hands to `updateCell()` — and the
                // two answer different questions for this column:
                //  - PERMISSION: the cell writes a Gestore Account slot, and
                //    the only catalogued key for that block is the TEAM's
                //    (`operator_id` was deleted from
                //    RequestManagementAuthorization, D-3), so this column is
                //    gated by the team's permission;
                //  - WRITE: still the single OPERATOR slot, never the whole
                //    team — WritesInlineEditableCells translates the cell
                //    back into `['operator_id' => $value]` before calling
                //    `updateWork()` (D-3b).
                // That translation is what keeps the spec 0086 mt06 lesson
                // intact: `editableField` is the LOGICAL key, never the DB
                // column, and `updateWork()` must RECOGNIZE the key it is
                // handed. Sending it a key it never learned (`supervisor_id`,
                // the first cut of spec 0086 D-3) produced a silent 200 no-op
                // — no write, no error. Passing `manager_slots` raw would
                // repeat the mistake in reverse: `updateWork()` does learn
                // that key now, but it expects an ordered slot LIST there and
                // the cell carries a single user id.
                //
                // `relation.scope` (direttiva utente 2026-07-23, extended by
                // the direttiva utente 2026-09-10): the picker is narrowed to
                // the operators of the row's OWN operational site AND
                // competent for the categories the offer requires — the
                // in-grid twin of the assignment popup's filtered picker,
                // `users/for-select?operational_site_id=<the offer's
                // site>&competence_category_ids[]=<its required categories>`.
                // The Sede comes from the visible `operational_site` column
                // (`quotes.operational_site_id`, spec 0113 D-4), the
                // categories from the non-visible key RequestAssignmentScope
                // projects. An offer missing either one keeps the full list on
                // that half (an empty requirement means "requires nothing",
                // not "nobody qualifies").
                'id' => self::OPERATOR_COLUMN_ID,
                'label' => 'requestManagement.columns.operator',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
                'editable' => true,
                'editableField' => 'manager_slots',
                'relation' => [
                    'resource' => 'users',
                    'scope' => [
                        'operational_site_id' => 'operational_site',
                        'competence_category_ids' => RequestAssignmentScope::CATEGORIES_KEY,
                    ],
                ],
                'nullable' => true,
            ],
            [
                // GA1 (direttiva utente 2026-09-08, which moved this column
                // off position 3): the slot right before the Operatore,
                // editable in-cell exactly like it — same picker,
                // same nullable "clear the slot" semantics, same per-tab
                // relabel. Three deliberate differences, each mirroring how
                // the FORM already treats the two slots:
                //  - NO `relation.scope`: only the Operatore slot is bound to
                //    the Sede operativa (`operatorSlotParams`), so this picker
                //    lists every user, like every other slot of
                //    ManagerSlotsField;
                //  - its own field key `manager_ga1_id` instead of
                //    `manager_slots`: `editableField` is both the permission
                //    key and the key `updateCell()` receives, so two columns
                //    sharing one key would be indistinguishable at write time
                //    (the very silent-no-op class of bug spec 0086 mt06
                //    documents). The role matrix therefore gates this slot on
                //    its own row — configure it alongside `manager_slots`;
                //  - no denormalized column and no assignment notification:
                //    GA1 scopes nothing (RequestManagementScope reads
                //    `operator_id` alone), and a move that leaves the operator
                //    in place assigns nobody — the rule the whole-team editor
                //    already follows (spec 0097, D-6/AC-007).
                'id' => self::GA1_COLUMN_ID,
                'label' => 'requestManagement.columns.managerGa1',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
                'editable' => true,
                'editableField' => 'manager_ga1_id',
                'relation' => ['resource' => 'users'],
                'nullable' => true,
            ],
        ];
    }
}

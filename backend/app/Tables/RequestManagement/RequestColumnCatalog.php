<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Rules\TaxCode;
use App\Tables\Shared\ProductsOfInterestColumn;

/**
 * Declarative column/filter/action catalogue for the `request-management`
 * domain (spec 0049): an OPERATIVE view over the same `opportunities` rows
 * (D-1, no new entity). The visible columns are the operator's worklist:
 *  - `source` ("Fonte", user directive 2026-07-31) — the own-FK relation
 *    column opening the worklist, sortable + set-filterable like every other
 *    relation-by-name column here, and inline-editable over
 *    `sources/for-select`.
 *  - `general_notes` ("Note generali", user directive 2026-07-31) — a real
 *    `opportunities` text column, sortable + text-filterable via the generic
 *    engine, display-only (this module never writes it).
 *  - `product_categories` ("Categoria prodotto") — AGGREGATED
 *    to-many via `productLines.productCategory`, filterable (set) but never
 *    sortable (no single related row to order by), and inline-editable
 *    (spec 0075) through the `product_lines` collection it projects.
 *  - `operator_ga2` ("Operatore") — the Account Manager at pivot position 2
 *    (GA2), display-only.
 *  - `workflow_status` ("Stato di lavorazione") — the related working-state
 *    row's name + color token for the badge, sortable + set-filterable.
 *  - `first_name`/`last_name`/`tax_code`/`phone` — the CLIENT's anagraphic
 *    fields, read from the Registry's PersonalData card (phone = its primary
 *    phone/mobile contact), inline-editable, and — user directive 2026-08-03
 *    — sortable + text-filterable + searchable like every other column here,
 *    all three resolved against that card by RequestClientColumns.
 *  - `next_callback_at` ("Prossimo richiamo", spec 0052 D-1/D-5) — a real
 *    `opportunities` column, sortable + date-filterable via the generic
 *    engine, mirroring `OpportunityColumnCatalog`'s `created_at`.
 *  - `operational_site` ("Sede operativa", spec 0056) — SPECIALLY-derived
 *    (the site has no own name), sortable + set-filterable via the shared
 *    App\Tables\Shared\OperationalSiteColumn, and inline-editable (user
 *    directive 2026-07-23) since it is what scopes the operator picker of the
 *    column right after it.
 * All derived/anagraphic values are resolved by
 * RequestManagementTableDefinition::mapRow() from eager-loaded relations. A
 * hidden `created_at` column exists solely to back the default sort.
 */
final class RequestColumnCatalog
{
    /**
     * Upper bound for an inline-edited client anagraphic value — the width of
     * the `personal_data`/`contacts` string columns it lands in, so the 422
     * arrives before the database complains.
     */
    private const int CLIENT_FIELD_MAX_LENGTH = 255;

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            // "Fonte" (user directive 2026-07-31): declared FIRST — the
            // request's provenance is what the operator reads before anything
            // else. Inline-editable through the SAME `sources/for-select`
            // picker LeadColumnCatalog's own `source` column already uses, and
            // writing the real FK (`source_id`), a field this module's
            // authorization already owns. NOT nullable on purpose: `source_id`
            // is mandatory there (required: true), and it is a criterion the
            // workflow resolution reads (spec 0047) — clearing it in-cell 422s
            // instead of silently un-setting it.
            [
                ...self::derivedColumn('source', 'requestManagement.columns.source'),
                'editable' => true,
                'editableField' => 'source_id',
                'relation' => ['resource' => 'sources'],
            ],
            // "Richieste di modifica in attesa" (spec 0078, AC-037): a
            // per-record pending-count badge/alert, declared right after
            // "Fonte" since it is today's only protected field. A real
            // aggregated value (withCount on the HasFieldChangeRequests
            // relation, RequestManagementTableDefinition::baseQuery()),
            // display-only — never editable, and not sortable/filterable
            // (no operative need for either in this microtask).
            [
                'id' => 'pending_change_requests',
                'label' => 'requestManagement.columns.pendingChangeRequests',
                'type' => 'number',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            // Inline-editable (user directive 2026-08-03, spec 0075 D-3 —
            // REVERSING spec 0055's "work-panel concern" call): the cell edits
            // the `product_lines` collection itself, in the SAME flow as the
            // form (a business function, then a category scoped by it), never
            // a free string of category names. `editableField` remaps both the
            // permission key and the written field onto `product_lines`, the
            // key RequestManagementAuthorization already owns as mandatory —
            // so clearing the classification in-cell is refused by the generic
            // engine's own required-field step, and every OTHER rule of the
            // set is enforced by RequestProductLineWriter, the one writer both
            // channels reach.
            [
                ...self::aggregatedColumn('product_categories', 'requestManagement.columns.productCategory'),
                'editable' => true,
                'editor' => 'product_lines',
                'editableField' => 'product_lines',
            ],
            // User directive 2026-07-23: the SAME "Prodotti di interesse"
            // column the opportunities grid declares (shared declaration), and
            // — unlike `product_categories` above — inline-editable: the
            // collection is a first-class operative field here, written through
            // updateWork() like every other cell of this domain.
            // `lockScope` (spec 0075, D-4): this module refuses a product
            // outside the request's own categories instead of covering it with
            // a new product line, so the in-cell picker never offers the
            // whole-catalogue escape the opportunities grid keeps.
            ProductsOfInterestColumn::declaration('requestManagement.columns.productsOfInterest', lockScope: true),
            // "Note generali" (user directive 2026-07-31): the opportunity's
            // own `general_notes` free text, right beside the products the
            // operator reads it against. A REAL DB column, so sorting and the
            // `text` filter both resolve through the generic engine with no
            // derived-column hook. Display-only, mirroring
            // RequestGeneralNotesCallout in the work panel: this module never
            // writes the field (the opportunities form owns it), so it is
            // deliberately absent from RequestManagementAuthorization::fields().
            [
                'id' => 'general_notes',
                'label' => 'requestManagement.columns.generalNotes',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
            ],
            // Inline-editable (user directive 2026-07-23): the site is picked
            // in-cell so the operator column right after it can be narrowed to
            // that site's own operators without leaving the grid. Same shape
            // LeadColumnCatalog already declares for its own `operational_site`
            // — a `/for-select` picker writing the real FK, nullable (clearing
            // the cell un-sets the site).
            [
                ...self::derivedColumn('operational_site', 'requestManagement.columns.operationalSite'),
                'editable' => true,
                'editableField' => 'operational_site_id',
                'relation' => ['resource' => 'operational-sites'],
                'nullable' => true,
            ],
            // "Trasferito" (spec 0079): a real, sortable/filterable boolean
            // column — the generic engine serves ordering, the `boolean`
            // filter and export with no derived-column hook. A system flag,
            // deliberately NOT `editable`: no inline-editor or endpoint of
            // this module accepts it in writing (AC-024).
            [
                'id' => 'is_transferred',
                'label' => 'requestManagement.columns.transferred',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                // Inline cell-editing (spec 0055, D-6): the same relation
                // column LeadColumnCatalog already declares for its operator —
                // an async `/for-select` picker over `users`, whose value is
                // the GA2 pivot row (`operator_id`), never a column on
                // `opportunities`. Nullable: clearing the cell un-assigns the
                // request (updateWork's applyOperator detaches).
                //
                // `relation.scope` (user directive 2026-07-23): the picker is
                // narrowed to the operators of the row's OWN operational site,
                // the in-grid twin of the work panel's site-filtered operator
                // field — `users/for-select?operational_site_id=<the row's
                // site>`. A row with no site keeps the full list.
                'id' => 'operator_ga2',
                'label' => 'requestManagement.columns.operator',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
                'editable' => true,
                'editableField' => 'operator_id',
                'relation' => [
                    'resource' => 'users',
                    'scope' => ['operational_site_id' => 'operational_site'],
                ],
                'nullable' => true,
            ],
            [
                // Inline cell-editing (spec 0054, D-4/D-5/D-6): the DISPLAYED
                // id ('workflow_status') differs from the WRITTEN field
                // ('opportunity_workflow_status_id', the only key present in
                // RequestManagementAuthorization), hence `editableField`. The
                // write goes through RequestManagementTableDefinition's
                // updateCell() override into
                // RequestManagementService::updateWork(), the single choke
                // point that enforces set-membership (AC-011) AND the
                // mandatory-note rule for a `requires_note` target status
                // (D-5) — never a plain `$row->update()`. `notable: true`
                // is this engine's ONLY column allowing a `note` in the PATCH
                // payload (D-5/AC-012). Not nullable: updateWork() has no
                // "clear the status" semantics, so `value: null` 422s rather
                // than silently no-op-ing.
                'id' => 'workflow_status',
                'label' => 'requestManagement.columns.workflowStatus',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'editable' => true,
                // Spec 0055, D-1/D-3: a SELECT over the options optionsFor()
                // resolves, not the text editor the `type` would imply — and
                // it is `editor`, not `editableField`, that tells the
                // validator this cell's value is an id.
                'editor' => 'select',
                'editableField' => 'opportunity_workflow_status_id',
                'notable' => true,
            ],
            // `format` (user directive 2026-07-23): an inline edit stores the
            // value in the SAME canonical shape the card form does — the
            // engine applies it before the rules run (CellValueValidator).
            self::clientColumn('first_name', 'requestManagement.columns.firstName', 'client_first_name', format: 'person_name'),
            self::clientColumn('last_name', 'requestManagement.columns.lastName', 'client_last_name', format: 'person_name'),
            // The inline editor sends the cell alone, so TaxCode can only check
            // format + control character here: the anagraphic-consistency check
            // needs the whole card and lives in ValidatesRequestClientProfile.
            self::clientColumn('tax_code', 'requestManagement.columns.taxCode', 'client_tax_code', [new TaxCode], 'tax_code'),
            self::clientColumn('phone', 'requestManagement.columns.phone', 'client_phone', format: 'phone'),
            [
                // Real DB column (spec 0052 D-1/D-5): the operator's planned
                // next contact, sortable/filterable via the generic engine
                // like OpportunityColumnCatalog's `created_at`. Inline
                // cell-editing (spec 0054, D-4): NOT in Opportunity::$fillable
                // (mass-assignment guard), so RequestManagementTableDefinition
                // overrides updateCell() to write it through
                // RequestManagementService::updateWork() — never a plain
                // `$row->update()` (spec 0052 D-4's reminder-marker invariant
                // lives there).
                'id' => 'next_callback_at',
                'label' => 'requestManagement.columns.nextCallbackAt',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                'editable' => true,
                // Spec 0055, D-4: a real date/time picker instead of the raw
                // `Y-m-d\TH:i` string the generic `datetime` editor used to
                // hand over.
                'editor' => 'datetime',
                'nullable' => true,
            ],
            [
                // Hidden: not shown, but a real sortable DB column so the
                // default "most recently loaded first" ordering (defaultSort,
                // user directive 2026-08-03: intake order, NOT last-touched)
                // resolves against a valid catalogue column — the generic
                // engine 422s an unknown sort colId.
                'id' => 'created_at',
                'label' => 'requestManagement.columns.createdAt',
                'type' => 'datetime',
                'visible' => false,
                'sortable' => true,
                'filterable' => false,
            ],
        ];
    }

    /**
     * A DERIVED (related-row-name) column declaration: filterable via the
     * `set` widget and sortable (a correlated subquery), mirroring
     * OpportunityColumnCatalog's own helper.
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
     * A to-many AGGREGATED (via `productLines`) column declaration:
     * filterable via `set` (whereHas on the related row's name) but never
     * sortable — no single related row to order by.
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
     * A CLIENT anagraphic column (spec 0055, D-7/D-8): displayed from the
     * Registry's PersonalData card by RequestRowMapper, written back through
     * RequestManagementService::updateWork() under its OWN field-permission
     * key (`client_*`) — four separate keys, so the role_field_permissions
     * matrix can open the phone without opening the tax code (user decision).
     *
     * Sortable + filterable + searchable (user directive 2026-08-03): these
     * four were the last columns of the grid an operator could not narrow from
     * the header. Since NONE of them is a real `opportunities` column, all
     * three hooks are DERIVED — RequestClientColumns translates them onto the
     * card relation (`whereHas`/correlated subquery), never a plain LIKE or
     * ORDER BY on a non-existent column. `filterType: text` therefore mounts
     * the same Excel-like widget every other text column here has (Set
     * checklist + typed conditions), backed by the same collaborator's
     * distinct values.
     *
     * `nullable` is true for all four on purpose: whether a value is MANDATORY
     * is not a property of the column but of the resolved field
     * (FieldPermission::$required, enforced by TableCellUpdateService step
     * 4.5). Declaring it here would freeze in the catalogue a rule the
     * matrix is supposed to own per role.
     *
     * @return array<string, mixed>
     */
    /**
     * @param  array<int, mixed>  $rules  extra rules on top of the shared length cap
     * @param  string|null  $format  canonical shape the inline editor stores the value in (see InputFormat)
     */
    private static function clientColumn(string $id, string $label, string $editableField, array $rules = [], ?string $format = null): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'text',
            'searchable' => true,
            'editable' => true,
            'editableField' => $editableField,
            'nullable' => true,
            'rules' => ['max:'.self::CLIENT_FIELD_MAX_LENGTH, ...$rules],
            ...($format === null ? [] : ['format' => $format]),
        ];
    }

    /**
     * Only the filterable columns produce a filter descriptor (display-only
     * text columns carry no `filterType`).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return array_values(array_map(
            static fn (array $column): array => [
                'columnId' => $column['id'],
                'type' => $column['filterType'],
            ],
            array_filter(
                self::columns(),
                static fn (array $column): bool => ($column['filterable'] ?? false) === true,
            ),
        ));
    }

    /**
     * `view` ("Lavora") and `documents` — no edit/delete (the CRUD boundary
     * stays on `opportunities.*`, never request-management). `documents`
     * reuses the polymorphic Attachment subsystem on the same Opportunity
     * record as the opportunities module, but is gated by this module's OWN
     * permission (`request-management.viewDocuments`, D-2) and carries the
     * per-row `documents_count` badge. `activity` (D-7, amended) opens this
     * module's OWN activity surface: the generic framework used to resolve its
     * Policy by MODEL CLASS (Opportunity), which is why the action did not
     * exist — it now goes through RequestManagementActivityAuthorizer, gated by
     * `request-management.viewActivity`. Declared LAST on purpose: with the
     * shared `INLINE_ACTION_LIMIT`, the fourth action falls into the overflow
     * (three-dots) menu, which is where consultation belongs.
     * `notes` (spec 0052 B4b) opens the collaborative-notes dialog: gated by
     * `request-management.view`, NOT a notes permission — reading a record's
     * notes is inherited from the ability to open the record (D-6), while
     * writing is separately authorized server-side by `notes.create` inside
     * the dialog itself. `count_field` (spec 0052 B4c, reversing the earlier
     * "out of scope" call) carries `notes_count` — every note on the record,
     * roots AND replies, soft-deleted excluded — mirroring `documents`'
     * `documents_count` badge.
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
                'permission' => 'request-management.view',
            ],
            [
                'key' => 'documents',
                'label' => 'actions.documents',
                'icon' => 'paperclip',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'request-management.viewDocuments',
                'count_field' => 'documents_count',
            ],
            [
                'key' => 'notes',
                'label' => 'actions.notes',
                'icon' => 'message-square',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'request-management.view',
                'count_field' => 'notes_count',
            ],
            // "Trasferisci contatto" (spec 0079): declared AFTER the first
            // three so it falls into the overflow (three-dots) menu
            // (INLINE_ACTION_LIMIT = 3, row-actions.tsx:33) — not frequent
            // enough for an inline slot. Opens AssignOperatorsDialog in its
            // `lockedMode="single"` shape, gated by its OWN ability
            // (transferContact), on top of `request-management.update`.
            [
                'key' => 'transfer-contact',
                'label' => 'actions.transferContact',
                'icon' => 'arrow-right-left',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'request-management.transferContact',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'request-management.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'request-management.viewActivity',
            ],
        ];
    }
}

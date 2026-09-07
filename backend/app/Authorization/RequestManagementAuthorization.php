<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `request-management` resource (spec 0049).
 *
 * The module has no dedicated model (it operates on Opportunity, D-1): the
 * abstract's contract only needs a resource key + field/action catalogue, no
 * Eloquent class, so this mirrors the smallest existing authorizations
 * (VatRatesAuthorization) with the operative fields the work panel writes
 * (D-4/D-5, `next_callback_at` added by spec 0054 D-4, on the Offerta since
 * the user directive 2026-09-04) — visible+editable when the actor may
 * write, else read-only. Only `source_id` and
 * `product_lines` are mandatory-restrictive (user directives 2026-07-29 /
 * 2026-07-31); every other field blocks on nothing here. Spec 0086:
 * `products_of_interest` is REMOVED — its replacement, `offer_lines`, is the
 * Offerta's own REVENUE row collection, WRITABLE from this module since the
 * user directive 2026-08-07 (the grid column stays a read-only projection).
 *
 * User directive 2026-08-07: `attribute_values` and `quote_workflow_status_id`
 * are BACK, no longer as the Opportunity's own dimensions (which specs 0084
 * D-1 / 0083 D-2 rightly removed) but as the Offerta's — the record this
 * module operates on since spec 0086. Same keys, same shape and same
 * ceiling rule as QuotesAuthorization declares for them.
 *
 * Spec 0097, D-3: `operator_id` is GONE from this catalogue, replaced by
 * `manager_slots` — the panel stopped editing the lone GA2 "Operatore" and
 * started editing the Offerta's whole team, of which that operator is slot
 * `ManagerPositions::OPERATOR`. Nothing became ungated: the grid column
 * `operator_ga2`, which still writes that one slot, now hangs off the new
 * key (see the field's own note below), and a migration renames the already
 * configured `role_field_permissions` rows so no administrator restriction
 * is lost in the move.
 *
 * Spec 0097, D-9 (user directive 2026-09-02): `supervisor_id` is BACK in this
 * catalogue, and that deliberately REVERSES spec 0087 D-13/D-14/INV-5, which
 * had stated this module "does not read, write or deduce
 * `quotes.supervisor_id`". The reversal is narrow, and the narrowing is the
 * whole point: what comes back is NOT the old ownership column. Since spec
 * 0087 that field means one thing only — the Offerta's commission-recipient
 * Supervisore (`CommissionRecipientRole::Supervisor`), a plain fillable
 * scalar on `quotes` — while the request's operative ownership stays
 * `operator_id`/the OPERATOR slot of `manager_slots`, which this key must
 * never touch. So no `RequestSupervisorWriter` comes back with it: the field
 * travels with `reporter_id`/`operational_site_id` through
 * RequestAttributionWriter::applyQuoteAttribution(), coupled to no pivot, no
 * assignment notification and no visibility scope (AC-014).
 */
class RequestManagementAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'request-management';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            // Spec 0054, D-4: written exclusively by
            // RequestManagementService::updateWork() (never mass-assigned —
            // Quote::$fillable deliberately excludes it, the guard spec 0052
            // D-2 set on the Opportunity and the user directive 2026-09-04
            // carried over with the column); this catalogue entry only closes
            // a gap in the per-field permission system, it grants nothing
            // new.
            new FieldDefinition('next_callback_at', 'date'),
            // "Funzione aziendale" + "categoria prodotto" (user directive
            // 2026-07-31): the same `product_lines` collection the create form
            // writes, made editable from the panel too. MANDATORY for the same
            // reason as products_of_interest — an opportunity must always
            // carry at least one row (ValidatesProductLines' `min:1` on both
            // write channels), so no role matrix may narrow it away.
            new FieldDefinition('product_lines', 'custom', mandatory: true),
            // Attribution block (user directive 2026-07-22): "Fonte",
            // "Segnalatore" and the GA2 "Operatore"/Supervisore — the same
            // dimensions the opportunities form owns, made editable from the
            // work panel too.
            // MANDATORY (user directive 2026-07-29): a request always knows
            // where it came from — the create form requires it and the panel
            // never lets it be cleared (UpdateRequestRequest's `required`).
            new FieldDefinition('source_id', 'select', mandatory: true),
            new FieldDefinition('reporter_id', 'select'),
            // "Supervisore" (spec 0097, D-9): the Offerta's commission
            // recipient, editable from this panel again — see the class
            // docblock for why that reverses spec 0087 and how narrowly.
            // NOT mandatory: an Offerta with no Supervisore is a legitimate
            // state, so no role matrix has to keep the field filled.
            new FieldDefinition('supervisor_id', 'select'),
            // `manager_slots`: the Offerta's WHOLE team of Gestori Account,
            // the key that REPLACED `operator_id` (spec 0097, D-3 — the
            // panel no longer exposes the lone GA2 picker, it exposes the
            // same ManagerSlotsField the Offerte form uses). Same
            // `multiselect` declaration QuotesAuthorization gives its own
            // `manager_slots`, so the two forms gate the identical block on
            // identical terms.
            //
            // The old key's semantics did NOT disappear, they widened: the
            // GA2 "Operatore" is slot `ManagerPositions::OPERATOR` of this
            // very collection, and the grid column `operator_ga2` — which
            // still WRITES that single slot — is now gated by THIS key
            // (`editableField` => `manager_slots`, RequestColumnCatalog).
            // The spec 0086 mt06 lesson that made `operator_id` the ONE key
            // of both channels still holds and is unchanged: `editableField`
            // is the LOGICAL permission key, never the DB column, and
            // `updateWork()` keeps recognizing exactly one WRITE key per
            // channel (`operator_id` for the cell/bulk/transfer, the new
            // `manager_slots` for the panel) — WritesInlineEditableCells
            // translates the cell's key back before calling it, so the two
            // can never silently decouple into a 200 no-op again.
            new FieldDefinition('manager_slots', 'multiselect'),
            // `manager_ga3_id` (direttiva utente 2026-09-07): the grid's GA3
            // cell — ONE slot of the very team `manager_slots` above gates as
            // a whole. It needs a key of its own because `editableField` is
            // BOTH the permission key and the key `updateCell()` receives:
            // two columns sharing `manager_slots` would be indistinguishable
            // at write time, the silent-no-op class of bug spec 0086 mt06
            // documents. Consequence to be aware of when configuring roles:
            // restricting `manager_slots` does NOT restrict this key, the two
            // rows of the matrix are independent.
            new FieldDefinition('manager_ga3_id', 'select'),
            // Spec 0056: the Sede operativa, editable from this same
            // attribution block (see OpportunitiesAuthorization's docblock for
            // the operational-sites.viewAny ceiling rule this field shares).
            new FieldDefinition('operational_site_id', 'select'),
            // Client anagraphic block (spec 0055, D-8, user decision): FOUR
            // separate keys rather than one `client_identity`, so the
            // role_field_permissions matrix can make the phone editable
            // without the tax code. None of them is a column on
            // `opportunities`: they address the client Registry's
            // PersonalData card (and its primary phone contact), written by
            // RequestClientProfileWriter through updateWork().
            new FieldDefinition('client_first_name', 'text'),
            new FieldDefinition('client_last_name', 'text'),
            new FieldDefinition('client_tax_code', 'text'),
            new FieldDefinition('client_phone', 'text'),
            // "Informazioni aggiuntive" (user directive 2026-08-07): ONE key
            // for the whole dynamic block, exactly as QuotesAuthorization
            // declares it — the per-code 422 rules live in
            // AttributeValueValidator, not in the role matrix.
            new FieldDefinition('attribute_values', 'custom'),
            // "Stato di lavorazione" (user directive 2026-08-07): the
            // Offerta's own operational status, advanced from this panel.
            new FieldDefinition('quote_workflow_status_id', 'select'),
            // "Linee dell'offerta" (user directive 2026-08-07): ONE key for
            // the whole REVENUE row collection, as QuotesAuthorization
            // declares its own — the per-row 422 rules live in
            // ValidatesQuoteLines, not in the role matrix. NOT mandatory: an
            // offer with no row is a legitimate state (spec 0086 AC-028).
            new FieldDefinition('offer_lines', 'custom'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['export', 'view_activity', 'transfer_contact'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);

        return [
            'next_callback_at' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'product_lines' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'source_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'reporter_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            // Spec 0097, D-9: same ceiling QuotesAuthorization gives its own
            // `supervisor_id` — the two forms gate the identical field on
            // identical terms, no extra ability on top.
            'supervisor_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'manager_slots' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'manager_ga3_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            // Spec 0056: readonly unless the actor ALSO holds
            // operational-sites.viewAny (mirrors OpportunitiesAuthorization).
            'operational_site_id' => $mayWrite && $actor->can('operational-sites.viewAny') ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'client_first_name' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'client_last_name' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'client_tax_code' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'client_phone' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'attribute_values' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'quote_workflow_status_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'offer_lines' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'export' => $actor->can('request-management.export'),
            // Gates the ActivityLogSection in the panel (spec 0034/0049 D-7);
            // the record-level `request-management.view` boundary is
            // enforced separately by GET /api/activity-log/request-management/{id}.
            'view_activity' => $model !== null && $actor->can('request-management.viewActivity'),
            // Spec 0079: gates the "Trasferisci contatto" button in the work
            // panel (Lavora). Per-record like `view_activity` above: the
            // action operates on an existing request, so it must stay false
            // on the create form (`$model === null`). Requires BOTH abilities
            // because it's a write: it must mirror the double gate enforced
            // server-side by RequestManagementController::transfer()
            // (`request-management.update` AND `.transferContact`), or this
            // flag would tell the UI an action is allowed that the endpoint
            // then rejects with 403. `export`/`view_activity` above are
            // read-only and don't need `update`.
            'transfer_contact' => $model !== null
                && $actor->can('request-management.update')
                && $actor->can('request-management.transferContact'),
        ];
    }
}

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
 * (D-4/D-5, `next_callback_at` added by spec 0054 D-4) — visible+editable
 * when the actor may write, else read-only. Only `source_id` and
 * `product_lines` are mandatory-restrictive (user directives 2026-07-29 /
 * 2026-07-31); every other field blocks on nothing here. Spec 0086:
 * `products_of_interest` is REMOVED — the module's grid column
 * (`offer_lines`) is read-only, derived from the Offerta's own REVENUE lines.
 *
 * User directive 2026-08-07: `attribute_values` and `quote_workflow_status_id`
 * are BACK, no longer as the Opportunity's own dimensions (which specs 0084
 * D-1 / 0083 D-2 rightly removed) but as the Offerta's — the record this
 * module operates on since spec 0086. Same keys, same shape and same
 * ceiling rule as QuotesAuthorization declares for them.
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
            // Opportunity::$fillable deliberately excludes it, spec 0052
            // D-2); this catalogue entry only closes a gap in the per-field
            // permission system, it grants nothing new.
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
            // `operator_id`: the ONE field key for the GA2 "Operatore"/
            // Supervisore, on BOTH write channels — the panel's PATCH wire
            // key AND the grid's `operator_ga2` column `editableField` (spec
            // 0086, corrected in execution: a first cut named this field
            // `supervisor_id` on the grid channel only, which silently
            // decoupled it from `RequestManagementService::updateWork()`'s
            // own `operator_id` check — the inline edit returned 200 without
            // writing anything. The DB column the write actually lands on is
            // `quotes.supervisor_id`, but that is RequestSupervisorWriter's
            // own internal detail (plus the GA2 pivot sync, D-3): it never
            // surfaces as a field-permission key.
            new FieldDefinition('operator_id', 'select'),
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
            'operator_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            // Spec 0056: readonly unless the actor ALSO holds
            // operational-sites.viewAny (mirrors OpportunitiesAuthorization).
            'operational_site_id' => $mayWrite && $actor->can('operational-sites.viewAny') ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'client_first_name' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'client_last_name' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'client_tax_code' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'client_phone' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'attribute_values' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'quote_workflow_status_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
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

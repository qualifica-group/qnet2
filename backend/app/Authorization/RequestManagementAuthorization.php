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
 * when the actor may write, else read-only. Only `products_of_interest`,
 * `source_id` and `product_lines` are mandatory-restrictive (user directives
 * 2026-07-23 / 2026-07-29 / 2026-07-31); every other field blocks on nothing here, its dedicated 422
 * rules living in AttributeValueValidator/ValidatesWorkflowStatus.
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
            new FieldDefinition('opportunity_workflow_status_id', 'select'),
            new FieldDefinition('attribute_values', 'custom'),
            // Spec 0054, D-4: written exclusively by
            // RequestManagementService::updateWork() (never mass-assigned —
            // Opportunity::$fillable deliberately excludes it, spec 0052
            // D-2); this catalogue entry only closes a gap in the per-field
            // permission system, it grants nothing new.
            new FieldDefinition('next_callback_at', 'date'),
            // "Prodotti di interesse": written by
            // RequestManagementService::updateWork() through
            // OpportunityProductInterestWriter. MANDATORY (user directive
            // 2026-07-23) exactly like in OpportunitiesAuthorization — the two
            // channels write the same collection, so the rule cannot differ.
            new FieldDefinition('products_of_interest', 'multiselect', mandatory: true),
            // "Funzione aziendale" + "categoria prodotto" (user directive
            // 2026-07-31): the same `product_lines` collection the create form
            // writes, made editable from the panel too. MANDATORY for the same
            // reason as products_of_interest — an opportunity must always
            // carry at least one row (ValidatesProductLines' `min:1` on both
            // write channels), so no role matrix may narrow it away.
            new FieldDefinition('product_lines', 'custom', mandatory: true),
            // Attribution block (user directive 2026-07-22): "Fonte",
            // "Segnalatore" and the GA2 "Operatore" — the same three
            // dimensions the opportunities form owns, made editable from the
            // work panel too. `operator_id` is NOT a column: it addresses the
            // `opportunity_user` pivot row at position
            // Opportunity::OPERATOR_MANAGER_POSITION (see
            // Opportunity::operatorManager()).
            // MANDATORY (user directive 2026-07-29): a request always knows
            // where it came from — the create form requires it and the panel
            // never lets it be cleared (UpdateRequestRequest's `required`).
            new FieldDefinition('source_id', 'select', mandatory: true),
            new FieldDefinition('reporter_id', 'select'),
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
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['export', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);

        return [
            'opportunity_workflow_status_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'attribute_values' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'next_callback_at' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'products_of_interest' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
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
        ];
    }
}

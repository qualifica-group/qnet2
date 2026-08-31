<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `quotes` resource (spec 0065, MT-05).
 *
 * Every field's ceiling is visible+editable when the actor may write
 * (create/update), else visible+readonly — EXCEPT `code` and `opportunity_id`:
 *
 *  - `code` (D-13, same pattern as ProjectsAuthorization/spec 0025): writable
 *    only in create ($model === null); once persisted it is permanently
 *    readonly, so EnforcesFieldPermissions rejects a changed `code` on
 *    update with a 422 (AC-069).
 *  - `opportunity_id` (AC-025): immutable once the quote exists — readonly
 *    whenever $model !== null, regardless of write ability (the FormRequest
 *    layer additionally rejects it outright via `prohibited` on update).
 *
 * `offer_lines`/`cost_lines` (D-8/D-11) are the two repeatable line sets: an
 * empty array is a legitimate, authoritative full-replace (AC-036), so
 * neither is `mandatory`.
 *
 * `attribute_values` (spec 0084): declared EXPLICITLY, unlike the former
 * Opportunity-level field it replaces — on the Opportunity it carried no
 * FieldDefinition at all and fell through EnforcesFieldPermissions'
 * permissive fallback (anything not catalogued is allowed). Here it is a
 * first-class field with the same visible/editable-when-may-write ceiling as
 * every other one.
 *
 * `manager_slots` (spec 0087, D-1): the Offerta's own Gestori Account, same
 * `multiselect`, non-mandatory ceiling as `manager_slots` on
 * OpportunitiesAuthorization — visible+editable when the actor may write,
 * else visible+readonly, no contextual exception.
 */
class QuotesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'quotes';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('code', 'text'),
            new FieldDefinition('title', 'text', mandatory: true),
            new FieldDefinition('opportunity_id', 'select', mandatory: true),
            new FieldDefinition('quote_workflow_status_id', 'select', mandatory: true),
            new FieldDefinition('commercial_id', 'select'),
            new FieldDefinition('reporter_id', 'select'),
            new FieldDefinition('supervisor_id', 'select'),
            new FieldDefinition('company_id', 'select'),
            new FieldDefinition('company_site_id', 'select'),
            new FieldDefinition('operational_site_id', 'select'),
            // Not mandatory (spec 0070, D-3): a preventivo is valid without a
            // layout, so a role may legitimately not see this field at all.
            new FieldDefinition('layout_id', 'select'),
            // Not mandatory either (user directive 2026-07-30): an offerta is
            // valid before the payment modality is agreed.
            new FieldDefinition('payment_method_id', 'select'),
            new FieldDefinition('internal_notes', 'textarea'),
            new FieldDefinition('manager_slots', 'multiselect'),
            new FieldDefinition('offer_lines', 'lines'),
            new FieldDefinition('cost_lines', 'lines'),
            new FieldDefinition('attribute_values', 'custom'),
            new FieldDefinition('commissions', 'collection'),
            new FieldDefinition('commission_recipient', 'select'),
            new FieldDefinition('commission_type', 'select'),
            new FieldDefinition('commission_value', 'number'),
            new FieldDefinition('commission_internal_note', 'textarea'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import', 'view_activity', 'generate_document'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);

        return [
            // Writable only on create (spec 0025/D-13): permanently readonly
            // once a $model exists, regardless of write ability.
            'code' => $mayWrite && $model === null ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'title' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            // Immutable once persisted (AC-025): readonly whenever a $model
            // already exists, regardless of write ability.
            'opportunity_id' => $mayWrite && $model === null ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'quote_workflow_status_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'commercial_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'reporter_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'supervisor_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'company_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'company_site_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'operational_site_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'layout_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'payment_method_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'internal_notes' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'manager_slots' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'offer_lines' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'cost_lines' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'attribute_values' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'commissions' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'commission_recipient' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'commission_type' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'commission_value' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'commission_internal_note' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('quotes.delete'),
            'export' => $actor->can('quotes.export'),
            'import' => $actor->can('quotes.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `quotes.view` boundary is enforced separately by
            // GET /api/activity-log/quotes/{id} itself.
            'view_activity' => $model !== null && $actor->can('quotes.viewActivity'),
            // spec 0070: `.docx` generation is a read (record-level), gated
            // by `quotes.view` like `view_activity` above, never `update`.
            'generate_document' => $model !== null && $actor->can('quotes.view'),
        ];
    }
}

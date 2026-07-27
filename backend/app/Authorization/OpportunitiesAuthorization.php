<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `opportunities` resource (spec 0040).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * LeadsAuthorization. `registry_id` is the mandatory scalar field (D-4);
 * `lead_id` is NOT permissionable (structural, immutable server-side
 * derivation — BR-1/BR-2) and carries no FieldDefinition here — `name`
 * (spec 0057, D-5) joins it: also structural/immutable (derived `OPP_{id}`),
 * so it carries no FieldDefinition either, not even a readonly one. Amendment
 * rev.3: the former `business_function_id`/`product_category_id` scalars are
 * REPLACED by a single `product_lines` field (a to-many collection) — which,
 * per user directive 2026-07-17, is itself mandatory (at least one row to
 * create; never clearable to empty), so it joins the non-restrictable set.
 * User directive 2026-07-17: `company_id`/`company_site_id` are REMOVED
 * entirely. `opportunity_status_id` (spec 0043, D-3) joins `name`/
 * `registry_id` as a mandatory field — the working-state FK is NOT NULL at
 * schema level, never hidable.
 *
 * Spec 0056, ONE contextual exception to "no contextual rules": unlike
 * `company_id`/`company_site_id`, `operational_site_id` is reintroduced, and
 * its ceiling ALSO requires `operational-sites.viewAny` — mirroring the table
 * engine's own invariant for relation columns (ResolvesEditableColumns::
 * mayPickRelationValue) at the FORM/panel level, since spec 0056 explicitly
 * keeps this field OUT of grid inline cell-editing (a relation column
 * declared `editable` there would either silently misfire in Gestione
 * Richieste's WritesInlineEditableCells override, which admits no relation
 * column, or start writing through Opportunities' base mass-assignment
 * engine — neither is the write path this spec chose: form + "Lavora" panel
 * only). Without this, an actor with `opportunities.update` but no read
 * access to Sedi operative would see an editable picker whose `/for-select`
 * call 403s.
 */
class OpportunitiesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'opportunities';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('registry_id', 'select', mandatory: true),
            new FieldDefinition('referent_id', 'select'),
            new FieldDefinition('commercial_id', 'select'),
            new FieldDefinition('reporter_id', 'select'),
            new FieldDefinition('supervisor_id', 'select'),
            new FieldDefinition('source_id', 'select'),
            new FieldDefinition('operational_site_id', 'select'),
            new FieldDefinition('opportunity_status_id', 'select', mandatory: true),
            new FieldDefinition('product_lines', 'multiselect', mandatory: true),
            // "Prodotti di interesse" (user directive 2026-07-23): MANDATORY —
            // an opportunity must carry at least one product, on every write
            // channel (form, work panel, inline cell edit).
            new FieldDefinition('products_of_interest', 'multiselect', mandatory: true),
            new FieldDefinition('manager_slots', 'multiselect'),
            new FieldDefinition('start_date', 'date'),
            new FieldDefinition('estimated_value', 'number'),
            new FieldDefinition('expected_close_date', 'date'),
            new FieldDefinition('success_probability', 'number'),
            new FieldDefinition('general_notes', 'textarea'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import', 'view_activity', 'view_documents'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);

        return [
            'registry_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'referent_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'commercial_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'reporter_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'supervisor_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'source_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            // Spec 0056: readonly unless the actor ALSO holds
            // operational-sites.viewAny (see class docblock) — never `required`.
            'operational_site_id' => $mayWrite && $actor->can('operational-sites.viewAny') ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'opportunity_status_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'product_lines' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'products_of_interest' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'manager_slots' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'start_date' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'estimated_value' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'expected_close_date' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'success_probability' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'general_notes' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        /** @var Opportunity|null $model */
        return [
            'delete' => $model !== null && $actor->can('opportunities.delete'),
            'export' => $actor->can('opportunities.export'),
            'import' => $actor->can('opportunities.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `opportunities.view` boundary is enforced
            // separately by GET /api/activity-log/opportunities/{id} itself.
            'view_activity' => $model !== null && $actor->can('opportunities.viewActivity'),
            // Gates the documents section in the detail (reused polymorphic
            // Attachment subsystem); the per-attachment boundary is enforced
            // separately by AttachmentPolicy on each attachment endpoint.
            'view_documents' => $model !== null && $actor->can('opportunities.viewDocuments'),
        ];
    }
}

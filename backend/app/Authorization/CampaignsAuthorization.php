<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `campaigns` resource (spec 0023, `code`
 * writable-on-create per spec 0025).
 *
 * Every field's ceiling is visible+editable when the actor may write
 * (create/update), else visible+readonly, mirroring ProjectsAuthorization —
 * EXCEPT `code` (spec 0025, BR-1): writable only in create ($model === null),
 * permanently readonly once persisted. The BR-2 derivation (`pipeline_status_id`
 * and `product_lines` forced null/empty/required depending on `project_id`)
 * is a VALUE-level rule enforced by the FormRequest + CampaignService, not a
 * field-permission concern — this class only answers "may this actor touch
 * this field at all", same as every other resource.
 *
 * Spec 0094, D-1/D-2: the former `business_function_id`/`product_category_id`
 * scalars are REPLACED by a single `product_lines` field (a to-many
 * collection), mirroring ProjectsAuthorization/OpportunitiesAuthorization.
 */
class CampaignsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'campaigns';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('code', 'text'),
            new FieldDefinition('project_id', 'select'),
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('partner_id', 'select'),
            new FieldDefinition('operational_site_id', 'select'),
            new FieldDefinition('pipeline_status_id', 'select'),
            new FieldDefinition('product_lines', 'multiselect'),
            new FieldDefinition('country_id', 'select'),
            new FieldDefinition('state_id', 'select'),
            new FieldDefinition('province_id', 'select'),
            new FieldDefinition('city_id', 'select'),
            new FieldDefinition('start_date', 'date', mandatory: true),
            new FieldDefinition('end_date', 'date'),
            new FieldDefinition('total_budget', 'number'),
            new FieldDefinition('target_lead', 'number'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);

        return [
            // code is writable only in create (spec 0025, BR-1): permanently
            // readonly once a $model exists, regardless of write ability. It is
            // required-on-create at the form level (manual entry with a
            // sequential auto-fill suggestion), so the create ceiling flags it
            // required; the Service still generates one when absent (fallback).
            'code' => $mayWrite && $model === null ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'project_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'partner_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'operational_site_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'pipeline_status_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'product_lines' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'country_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'state_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'province_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'city_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'start_date' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'end_date' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'total_budget' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'target_lead' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        /** @var Campaign|null $model */
        return [
            'delete' => $model !== null && $actor->can('campaigns.delete'),
            'export' => $actor->can('campaigns.export'),
            'import' => $actor->can('campaigns.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `campaigns.view` boundary is enforced separately by
            // GET /api/activity-log/campaigns/{id} itself.
            'view_activity' => $model !== null && $actor->can('campaigns.viewActivity'),
        ];
    }
}

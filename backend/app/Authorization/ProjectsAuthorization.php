<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `projects` resource (spec 0023, `code`
 * writable-on-create per spec 0025).
 *
 * Every field's ceiling is visible+editable when the actor may write
 * (create/update), else visible+readonly — EXCEPT `code` (spec 0025, BR-1):
 * writable only in create ($model === null); once persisted it is
 * permanently readonly, enforced by the same ceiling so
 * EnforcesFieldPermissions rejects a changed `code` on update with a 422.
 *
 * spec 0039, D-3: `pipeline_status_id` is NO LONGER mandatory — the FK went
 * from `required` to `nullable` in StoreProjectRequest (server-side fallback
 * to the system_key='new' status when omitted, ProjectService::create()).
 *
 * Spec 0094, D-1/D-2: the former `business_function_id`/`product_category_id`
 * scalars are REPLACED by a single `product_lines` field (a to-many
 * collection), mirroring OpportunitiesAuthorization's own amendment rev.3
 * field.
 */
class ProjectsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'projects';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('code', 'text'),
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('pipeline_status_id', 'select'),
            new FieldDefinition('product_lines', 'multiselect', mandatory: true),
            new FieldDefinition('country_id', 'select', mandatory: true),
            new FieldDefinition('state_id', 'select'),
            new FieldDefinition('province_id', 'select'),
            new FieldDefinition('city_id', 'select'),
            new FieldDefinition('partner_id', 'select'),
            new FieldDefinition('operational_site_id', 'select'),
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
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'pipeline_status_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'product_lines' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'country_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'state_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'province_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'city_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'partner_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'operational_site_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
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
        /** @var Project|null $model */
        return [
            'delete' => $model !== null && $actor->can('projects.delete'),
            'export' => $actor->can('projects.export'),
            'import' => $actor->can('projects.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `projects.view` boundary is enforced separately by
            // GET /api/activity-log/projects/{id} itself.
            'view_activity' => $model !== null && $actor->can('projects.viewActivity'),
        ];
    }
}

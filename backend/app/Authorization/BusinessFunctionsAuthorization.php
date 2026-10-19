<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `business-functions` resource (spec 0010).
 *
 * No contextual rules (no protected system row, unlike RolesAuthorization's
 * super-admin special case): every field's ceiling is simply
 * visible+editable when the actor may write (create/update), else
 * visible+readonly, mirroring the AbstractResourceAuthorization default.
 */
class BusinessFunctionsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'business-functions';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('type', 'select'),
            new FieldDefinition('manager_id', 'select'),
            new FieldDefinition('parent_id', 'select'),
            new FieldDefinition('is_active', 'boolean'),
            new FieldDefinition('users', 'multiselect'),
            new FieldDefinition('operational_sites', 'multiselect'),
            new FieldDefinition('company_ids', 'multiselect'),
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
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'type' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'manager_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'parent_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'users' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'operational_sites' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'company_ids' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('business-functions.delete'),
            'export' => $actor->can('business-functions.export'),
            'import' => $actor->can('business-functions.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `business-functions.view` boundary is enforced
            // separately by GET /api/activity-log/business-functions/{id}.
            'view_activity' => $model !== null && $actor->can('business-functions.viewActivity'),
        ];
    }
}

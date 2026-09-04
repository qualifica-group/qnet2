<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `task-categories` resource (spec 0101, D-4).
 *
 * No contextual rules: every field's ceiling is visible+editable when the
 * actor may write (create/update), else visible+readonly, mirroring
 * ContractStatusesAuthorization. `sort_order` are absent from
 * fields(): server-managed / never client-writable, so they are neither
 * permissionable nor submittable (AC-045).
 */
class TaskCategoriesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'task-categories';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('color', 'color', mandatory: true),
            new FieldDefinition('icon', 'text'),
            new FieldDefinition('is_active', 'boolean'),
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
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'color' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'icon' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('task-categories.delete'),
            'export' => $actor->can('task-categories.export'),
            'import' => $actor->can('task-categories.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `task-categories.view` boundary is enforced separately by
            // GET /api/activity-log/task-categories/{id}.
            'view_activity' => $model !== null && $actor->can('task-categories.viewActivity'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `task-templates` resource (spec 0124).
 *
 * Every field's ceiling is visible+editable when the actor may write
 * (create/update), else visible+readonly — no field is create-only or
 * permanently immutable, unlike ProductTypologiesAuthorization's `code`.
 * `items` is `custom` (a row collection, not a scalar/select input),
 * mirroring ProductCategoriesAuthorization's `attributes`.
 */
class TaskTemplatesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'task-templates';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('is_active', 'boolean'),
            new FieldDefinition('items', 'custom'),
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
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'items' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('task-templates.delete'),
            'export' => $actor->can('task-templates.export'),
            'import' => $actor->can('task-templates.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `task-templates.view` boundary is enforced
            // separately by GET /api/activity-log/task-templates/{id}.
            'view_activity' => $model !== null && $actor->can('task-templates.viewActivity'),
        ];
    }
}

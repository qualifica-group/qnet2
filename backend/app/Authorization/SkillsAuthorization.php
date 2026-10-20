<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `skills` resource (spec 0286): every field is
 * visible+editable when the actor may write (create/update), else
 * visible+readonly.
 */
class SkillsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'skills';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('category', 'text'),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('is_active', 'boolean'),
            new FieldDefinition('job_roles', 'multiselect'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $editable = fn (bool $required = false): FieldPermission => $mayWrite
            ? FieldPermission::visibleEditable(required: $required)
            : FieldPermission::visibleReadonly();

        return [
            'name' => $editable(true),
            'category' => $editable(),
            'description' => $editable(),
            'is_active' => $editable(),
            'job_roles' => $editable(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('skills.delete'),
            'view_activity' => $model !== null && $actor->can('skills.viewActivity'),
        ];
    }
}

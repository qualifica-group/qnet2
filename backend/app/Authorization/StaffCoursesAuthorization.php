<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `staff-courses` resource (spec 0284): every
 * field is visible+editable when the actor may write (create/update), else
 * visible+readonly.
 */
class StaffCoursesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'staff-courses';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('duration_minutes', 'number'),
            new FieldDefinition('validity_months', 'number'),
            new FieldDefinition('is_active', 'boolean'),
            new FieldDefinition('job_role_ids', 'multiselect'),
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
            'description' => $editable(),
            'duration_minutes' => $editable(),
            'validity_months' => $editable(),
            'is_active' => $editable(),
            'job_role_ids' => $editable(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('staff-courses.delete'),
            'view_activity' => $model !== null && $actor->can('staff-courses.viewActivity'),
        ];
    }
}

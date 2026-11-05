<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `revenue-types` resource (spec 0348, D-13):
 * the revenue sub-accounts. Every field is visible+editable when the actor may
 * write, else visible+readonly.
 */
class RevenueTypesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'revenue-types';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('chart_account_id', 'select', mandatory: true),
            new FieldDefinition('code', 'text', mandatory: true),
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('is_active', 'boolean'),
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

        return [
            'chart_account_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'code' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('revenue-types.delete'),
            'view_activity' => $model !== null && $actor->can('revenue-types.viewActivity'),
        ];
    }
}

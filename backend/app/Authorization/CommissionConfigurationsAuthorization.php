<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CommissionConfigurationsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'commission-configurations';
    }

    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('recipient_role', 'select', mandatory: true),
            new FieldDefinition('application_scope', 'select', mandatory: true),
            new FieldDefinition('product_category_id', 'select'),
            new FieldDefinition('product_id', 'select'),
            new FieldDefinition('recipient_id', 'select'),
            new FieldDefinition('commission_type', 'select', mandatory: true),
            new FieldDefinition('value', 'number', mandatory: true),
            new FieldDefinition('priority', 'number', mandatory: true),
            new FieldDefinition('valid_from', 'date', mandatory: true),
            new FieldDefinition('valid_until', 'date'),
            new FieldDefinition('status', 'select', mandatory: true),
            new FieldDefinition('internal_note', 'textarea'),
        ];
    }

    public function actions(): array
    {
        return ['delete', 'export', 'view_activity'];
    }

    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $permissions = [];

        foreach ($this->fields() as $field) {
            $permissions[$field->key] = $mayWrite
                ? FieldPermission::visibleEditable(required: $field->mandatory)
                : FieldPermission::visibleReadonly();
        }

        return $permissions;
    }

    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('commission-configurations.delete'),
            'export' => $actor->can('commission-configurations.export'),
            'view_activity' => $model !== null && $actor->can('commission-configurations.viewActivity'),
        ];
    }
}

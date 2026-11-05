<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `treasury-movements` resource (spec 0360): every
 * field is visible+editable when the actor may write, else visible+readonly.
 */
class TreasuryMovementsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'treasury-movements';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('type', 'select', mandatory: true),
            new FieldDefinition('direction', 'select'),
            new FieldDefinition('movement_date', 'date', mandatory: true),
            new FieldDefinition('financial_account_id', 'select', mandatory: true),
            new FieldDefinition('counterpart_account_id', 'select'),
            new FieldDefinition('amount', 'text', mandatory: true),
            new FieldDefinition('description', 'text', mandatory: true),
            new FieldDefinition('cost_type_id', 'select'),
            new FieldDefinition('revenue_type_id', 'select'),
            new FieldDefinition('treasury_type_id', 'select'),
            new FieldDefinition('registry_id', 'select'),
            new FieldDefinition('work_order_id', 'select'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);
        $permissions = [];

        foreach ($this->fields() as $field) {
            $permissions[$field->key] = match (true) {
                ! $mayWrite => FieldPermission::visibleReadonly(),
                $field->mandatory => FieldPermission::visibleEditable(required: true),
                default => FieldPermission::visibleEditable(),
            };
        }

        return $permissions;
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('treasury-movements.delete'),
            'export' => $actor->can('treasury-movements.export'),
            'view_activity' => $model !== null && $actor->can('treasury-movements.viewActivity'),
        ];
    }
}

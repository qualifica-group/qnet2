<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `commission-accruals` resource (spec 0212,
 * D-10). The ledger is read-only for everyone: both amounts are
 * visible-readonly and the Role matrix can only hide them.
 */
class CommissionAccrualsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'commission-accruals';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('base_amount', 'number'),
            new FieldDefinition('amount', 'number'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['export'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $permissions = [];

        foreach ($this->fields() as $field) {
            $permissions[$field->key] = FieldPermission::visibleReadonly();
        }

        return $permissions;
    }
}

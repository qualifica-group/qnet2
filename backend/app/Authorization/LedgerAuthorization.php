<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `ledger` resource (spec 0266): the registry
 * ledger is a read-only page gated by `ledger.view` alone. Registered only so
 * the permission is assignable from the Role form (AssignablePermissionCatalogue)
 * under its menu area: no form, hence no field and no action.
 */
class LedgerAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'ledger';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        return [];
    }
}

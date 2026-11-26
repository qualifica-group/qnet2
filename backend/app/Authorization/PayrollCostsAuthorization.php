<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `payroll-costs` resource (spec 0430, D-12): a
 * read-only grid plus the monthly import, gated by `payroll-costs.view` and
 * `payroll-costs.import`. Registered only so the permissions are assignable
 * from the Role form under the menu entry: no form, hence no field and no action.
 */
class PayrollCostsAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'payroll-costs';
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

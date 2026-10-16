<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `management-control` resource (spec 0268): the
 * module is read-only and gated by `management-control.view` alone. Registered
 * only so the permission is assignable from the Role form under its menu area:
 * no form, hence no field and no action.
 */
class ManagementControlAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'management-control';
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

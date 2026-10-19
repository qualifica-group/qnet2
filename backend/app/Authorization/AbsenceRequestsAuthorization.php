<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `absence-requests` resource (spec 0278),
 * registered only so `viewAny|create|manageAll` are assignable from the Role
 * form (AssignablePermissionCatalogue): the request form is not metadata
 * driven, hence no field and no action.
 */
class AbsenceRequestsAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'absence-requests';
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

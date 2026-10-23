<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `staff-trainings` resource (spec 0284),
 * registered only so `viewAny|viewAll|manage` are assignable from the
 * Role form: the training form is not metadata driven, hence no field and no
 * action.
 */
class StaffTrainingsAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'staff-trainings';
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

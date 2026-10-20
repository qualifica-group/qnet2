<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `user-skills` resource (spec 0286),
 * registered only so `viewAny|viewTeam|viewAll|manage` are assignable from the
 * Role form: the user skill form is not metadata driven, hence no field and no
 * action.
 */
class UserSkillsAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'user-skills';
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

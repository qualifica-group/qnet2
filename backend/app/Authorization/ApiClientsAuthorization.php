<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `api-clients` resource (spec 0209): the admin
 * of the external API integration clients. Registered only so the four
 * `api-clients.*` permissions are assignable from the Role form
 * (AssignablePermissionCatalogue): no form, hence no field and no action.
 */
class ApiClientsAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'api-clients';
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

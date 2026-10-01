<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `request-statistics` resource (spec 0185):
 * "Statistiche Gestione Richieste" is a read-only dashboard over the
 * request-management aggregates, gated by `request-statistics.view` alone.
 * Registered only so the permission is assignable from the Role form
 * (AssignablePermissionCatalogue): no form, hence no field and no action.
 */
class RequestStatisticsAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'request-statistics';
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

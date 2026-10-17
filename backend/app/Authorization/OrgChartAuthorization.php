<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `org-chart` resource (spec 0273): the
 * "Organigramma" page is a resource-less view gated by `org-chart.view` alone.
 * Registered only so the permission is assignable from the Role form
 * (AssignablePermissionCatalogue): no form, hence no field and no action.
 * The moves it offers are governed by the permissions of the data they edit.
 */
class OrgChartAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'org-chart';
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

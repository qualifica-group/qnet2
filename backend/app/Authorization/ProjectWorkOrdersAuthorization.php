<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use App\Services\WorkOrders\WorkOrderTypeAccess;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `project-work-orders` resource (user decision
 * 2026-11-25), registered only so `viewAny|view` are assignable from the Role
 * form, apart from the "Commesse" ones. The form and its fields are the work
 * orders' own (WorkOrdersAuthorization), hence no field and no action here.
 */
class ProjectWorkOrdersAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return WorkOrderTypeAccess::PROJECT_RESOURCE;
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

<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `stock` resource (spec 0307),
 * registered only so `viewAny|manage` are assignable from the Role form: the
 * movement form is not metadata driven, hence no field and no action.
 */
class StockAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'stock';
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

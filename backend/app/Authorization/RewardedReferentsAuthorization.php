<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `rewarded-referents` resource (spec 0059,
 * D-6). Registered in config/authorization.php so `GET /api/meta/
 * rewarded-referents` resolves (grid framework conventions), but the module
 * is READ-ONLY (scope/out: no create/update endpoint, no editable grid
 * column): `fields()` is deliberately EMPTY — there is nothing a form could
 * ever submit against this resource — and `fieldPermissionCeiling()` mirrors
 * that with an empty map. Only `export`/`view_activity` are real actions;
 * mirrors VatRatesAuthorization/RequestManagementAuthorization's shape for a
 * resource with no dedicated write surface.
 */
class RewardedReferentsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'rewarded-referents';
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
        return ['export', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        return [];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'export' => $actor->can('rewarded-referents.export'),
            'view_activity' => $model !== null && $actor->can('rewarded-referents.viewActivity'),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\User;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;
use Spatie\Permission\Models\Permission;

class AbilitiesService
{
    public function __construct(private readonly TimeEntrySubordinateResolver $subordinates) {}

    /**
     * Full ability map for the user: every defined permission with a boolean,
     * plus the user's role names and the derived `has_subordinates` signal
     * (spec 0309 D-7: at least one ACTIVE descendant in the org chart).
     *
     * @return array{roles: array<int, string>, permissions: array<string, bool>, has_subordinates: bool}
     */
    public function for(User $user): array
    {
        $hasSubordinates = $this->subordinates->descendantIds($user->id) !== [];

        if ($user->hasRole('super-admin')) {
            $permissions = Permission::orderBy('name')
                ->pluck('name')
                ->mapWithKeys(fn (string $name) => [$name => true])
                ->all();

            return [
                'roles' => $user->getRoleNames()->all(),
                'permissions' => $permissions,
                'has_subordinates' => $hasSubordinates,
            ];
        }

        $granted = $user->getAllPermissions()->pluck('name')->flip();

        $permissions = Permission::orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn (string $name) => [$name => $granted->has($name)])
            ->all();

        return [
            'roles' => $user->getRoleNames()->all(),
            'permissions' => $permissions,
            'has_subordinates' => $hasSubordinates,
        ];
    }
}

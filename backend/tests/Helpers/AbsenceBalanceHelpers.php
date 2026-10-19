<?php

declare(strict_types=1);

use App\Models\EmploymentProfile;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('balancePerson')) {
    /**
     * A user with an employment profile hired/terminated on the given dates
     * (spec 0278 balances).
     */
    function balancePerson(?string $hiredAt, ?string $terminatedAt = null): User
    {
        $user = User::factory()->create();
        EmploymentProfile::factory()->for($user)->create(['hired_at' => $hiredAt, 'terminated_at' => $terminatedAt]);

        return $user;
    }
}

if (! function_exists('grantAbsencePermissions')) {
    /**
     * @param  array<int, string>  $permissions
     */
    function grantAbsencePermissions(User $user, array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->givePermissionTo($permissions);
    }
}

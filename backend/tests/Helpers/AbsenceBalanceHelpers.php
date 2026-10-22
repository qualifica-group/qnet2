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

if (! function_exists('balanceDailyPerson')) {
    /**
     * A user with a profile working the given minutes per day, no break
     * (spec 0304 D-12 default allowance in days).
     */
    function balanceDailyPerson(int $dailyMinutes, ?string $name = null): User
    {
        $user = User::factory()->create($name === null ? [] : ['name' => $name]);
        EmploymentProfile::factory()->for($user)->create([
            'hired_at' => '2020-01-01',
            'terminated_at' => null,
            'standard_daily_minutes' => $dailyMinutes,
            'break_daily_minutes' => 0,
        ]);

        return $user;
    }
}

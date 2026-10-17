<?php

declare(strict_types=1);

use App\Models\EmploymentProfile;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('orgChartActor')) {
    /**
     * A user holding exactly the given permission names (spec 0273).
     *
     * @param  array<int, string>  $permissions
     */
    function orgChartActor(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return User::factory()->create()->givePermissionTo($permissions);
    }
}

if (! function_exists('orgChartPerson')) {
    /**
     * An active user with an employment profile reporting to the given managers.
     *
     * @param  array<string, mixed>  $attributes
     */
    function orgChartPerson(string $name, array $attributes = [], User ...$managers): User
    {
        $user = User::factory()->create(['name' => $name, 'is_active' => true]);
        EmploymentProfile::factory()->for($user)->reportsTo(...$managers)->create($attributes);

        return $user;
    }
}

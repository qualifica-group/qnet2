<?php

use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\Guard;
use Spatie\Permission\Models\Permission;

if (! function_exists('registryCard')) {
    /**
     * The personal-data card of a fresh registry: its contacts and addresses
     * follow the `registries` authorization (spec 0328).
     */
    function registryCard(): PersonalData
    {
        return Registry::factory()->withPersonalData()->create()->personalData;
    }
}

if (! function_exists('registryCardActor')) {
    /**
     * A non super-admin actor holding exactly the given `registries.*`
     * abilities through one role, with the given field-matrix rows of that
     * role on `registries` (field => visible/editable).
     *
     * @param  array<int, string>  $abilities
     * @param  array<string, array{visible: bool, editable: bool}>  $fieldRows
     */
    function registryCardActor(array $abilities, array $fieldRows = []): User
    {
        $role = Role::findOrCreate('registry-card-actor', Guard::getDefaultName(User::class));

        foreach ($abilities as $ability) {
            $role->givePermissionTo(Permission::findOrCreate("registries.{$ability}"));
        }

        foreach ($fieldRows as $field => $flags) {
            $role->fieldPermissions()->create(['resource' => 'registries', 'field' => $field, ...$flags, 'required' => false]);
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}

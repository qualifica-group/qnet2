<?php

declare(strict_types=1);

use App\Models\Referent;
use App\Models\Role;
use App\Models\RoleFieldPermission;
use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('agentFiscalProfileUserWith')) {
    /**
     * A user holding exactly the given agent-fiscal-profiles abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function agentFiscalProfileUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity'] as $ability) {
            Permission::findOrCreate("agent-fiscal-profiles.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("agent-fiscal-profiles.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('agentFiscalProfilePayload')) {
    /**
     * A valid write payload (withholding on, ENASARCO off, open-ended from 2026-01-01).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function agentFiscalProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'referent_id' => Referent::factory()->create()->id,
            'withholding_enabled' => true,
            'withholding_rate' => '23.00',
            'withholding_base_percent' => '50.00',
            'enasarco_enabled' => false,
            'enasarco_mandate_type' => null,
            'valid_from' => '2026-01-01',
            'valid_until' => null,
            'notes' => null,
        ], $overrides);
    }
}

if (! function_exists('restrictAgentFiscalProfileField')) {
    /**
     * Gives the user a role that narrows one field of the resource.
     */
    function restrictAgentFiscalProfileField(User $user, string $field, bool $visible, bool $editable): void
    {
        $role = Role::create(['name' => "restricted-{$field}-".uniqid()]);
        $user->assignRole($role);
        RoleFieldPermission::query()->create([
            'role_id' => $role->id, 'resource' => 'agent-fiscal-profiles', 'field' => $field,
            'visible' => $visible, 'editable' => $editable, 'required' => false,
        ]);
    }
}

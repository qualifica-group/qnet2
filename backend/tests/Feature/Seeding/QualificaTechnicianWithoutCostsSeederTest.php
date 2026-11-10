<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\LegacyRoleCatalogue;
use Database\Seeders\QualificaTechnicianWithoutCostsSeeder as Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

/** Spec 0380: the listed technicians lose the commessa costs and proforma; the others are unchanged. */
uses(RefreshDatabase::class);

function technicianRoleWithCosts(): Role
{
    $role = Role::create(['name' => LegacyRoleCatalogue::TECHNICIAN_ROLE]);
    foreach (['work-orders.view', 'work-orders.viewContractData', ...Seeder::WITHHELD_PERMISSIONS] as $permission) {
        $role->givePermissionTo(Permission::findOrCreate($permission));
    }
    $role->fieldPermissions()->create(['resource' => 'opportunities', 'field' => 'lead_id', 'visible' => false, 'editable' => false, 'required' => false]);

    return $role;
}

it('moves the listed technicians to the twin role without costs and proforma, idempotently, and leaves the others alone', function () {
    $source = technicianRoleWithCosts();
    Role::create(['name' => 'operatore-base']);
    $listed = User::factory()->create(['email' => Seeder::USERS[0]]);
    $listed->assignRole([$source, 'operatore-base']);
    $other = User::factory()->create(['email' => 'other.technician@example.com']);
    $other->assignRole($source);

    $this->seed(Seeder::class);
    $this->seed(Seeder::class);

    $twin = Role::findByName(Seeder::ROLE);
    $listed->refresh();

    expect(Role::query()->where('name', Seeder::ROLE)->count())->toBe(1)
        ->and($listed->getRoleNames()->sort()->values()->all())->toBe(['operatore-base', Seeder::ROLE])
        ->and($listed->hasPermissionTo('work-orders.viewCosts'))->toBeFalse()
        ->and($listed->hasPermissionTo('work-orders.viewInvoices'))->toBeFalse()
        ->and($listed->hasPermissionTo('work-orders.viewContractData'))->toBeTrue()
        ->and($twin->fieldPermissions()->get(['resource', 'field', 'visible'])->toArray())
        ->toBe([['resource' => 'opportunities', 'field' => 'lead_id', 'visible' => false]])
        ->and($other->refresh()->hasPermissionTo('work-orders.viewCosts'))->toBeTrue()
        ->and($other->hasPermissionTo('work-orders.viewInvoices'))->toBeTrue();
});

it('does nothing without the technician role', function () {
    $this->seed(Seeder::class);

    expect(Role::query()->where('name', Seeder::ROLE)->exists())->toBeFalse();
});

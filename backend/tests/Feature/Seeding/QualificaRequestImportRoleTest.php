<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\OperatorRoleCatalogue;
use Database\Seeders\QualificaCatalog\OperatorRoster;
use Database\Seeders\QualificaCatalog\RequestImporterRoleCatalogue as Importer;
use Database\Seeders\QualificaRoleResetSeeder;
use Database\Seeders\QualificaRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('0348: no mansione and not even admin holds the request import; the importer role holds only that', function () {
    $this->seed(QualificaRoleSeeder::class);

    $mansioni = [...array_keys(OperatorRoleCatalogue::ROLES), OperatorRoleCatalogue::ADMIN_ROLE];

    foreach ($mansioni as $name) {
        expect(Role::findByName($name)->hasPermissionTo(Importer::PERMISSION))->toBeFalse($name);
    }

    expect(Role::findByName(Importer::ROLE)->permissions()->pluck('name')->all())->toBe([Importer::PERMISSION])
        ->and(Role::findByName(Importer::ROLE)->description)->toBe(Importer::DESCRIPTION);
});

it('0348: re-running the role seeder takes the import away from a role that held it', function () {
    $this->seed(QualificaRoleSeeder::class);
    $supervisor = Role::findByName(OperatorRoleCatalogue::SUPERVISOR_ROLE);
    $supervisor->givePermissionTo(Importer::PERMISSION);

    $this->seed(QualificaRoleSeeder::class);

    expect($supervisor->fresh()->hasPermissionTo(Importer::PERMISSION))->toBeFalse();
});

it('0348: the role reset gives the roster extra role on top of the supervisor; another supervisor gets none', function () {
    $importers = collect(array_keys(OperatorRoster::EXTRA_ROLES))
        ->map(fn (string $email): User => User::factory()->create(['email' => $email, 'is_active' => true]));
    $other = User::factory()->create(['email' => 'giovanna.gervasio@qualificagroup.com', 'is_active' => true]);

    $this->seed(QualificaRoleResetSeeder::class);

    foreach ($importers as $user) {
        expect($user->fresh()->getRoleNames()->sort()->values()->all())->toBe([Importer::ROLE, OperatorRoleCatalogue::SUPERVISOR_ROLE])
            ->and($user->fresh()->can(Importer::PERMISSION))->toBeTrue();
    }

    expect($other->fresh()->getRoleNames()->all())->toBe([OperatorRoleCatalogue::SUPERVISOR_ROLE])
        ->and($other->fresh()->can(Importer::PERMISSION))->toBeFalse();
});

<?php

use App\Models\ContractType;
use App\Models\Role;
use Database\Seeders\QualificaCatalog\HrOfficeRoleCatalogue;
use Database\Seeders\QualificaContractTypeSeeder;
use Database\Seeders\QualificaRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('0290 AC-004: the seeder creates the four types in order and a re-run does not duplicate', function () {
    $this->seed(QualificaContractTypeSeeder::class);
    $this->seed(QualificaContractTypeSeeder::class);

    $types = ContractType::query()->orderBy('sort_order')->get(['name', 'sort_order']);

    expect($types->pluck('name')->all())->toBe(['Tempo determinato', 'Tempo indeterminato', 'Apprendistato', 'Tirocinio'])
        ->and($types->pluck('sort_order')->all())->toBe([1, 2, 3, 4]);
});

it('0290 AC-004: the HR office role holds the contract-types permissions', function () {
    $this->seed(QualificaRoleSeeder::class);
    $role = Role::findByName(HrOfficeRoleCatalogue::ROLE);

    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        expect($role->hasPermissionTo("contract-types.{$ability}"))->toBeTrue($ability);
    }
});

<?php

use App\Models\ContractType;
use App\Models\Role;
use Database\Seeders\QualificaCatalog\HrOfficeRoleCatalogue;
use Database\Seeders\QualificaContractTypeSeeder;
use Database\Seeders\QualificaRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// REQUIREMENT CHANGED (spec 0291 D-2): the internship is a relationship type, so the seeder
// creates three types instead of four and never touches an already loaded "Tirocinio".
it('0291 AC-005: the seeder creates the three types in order and a re-run does not duplicate', function () {
    $this->seed(QualificaContractTypeSeeder::class);
    $this->seed(QualificaContractTypeSeeder::class);

    $types = ContractType::query()->orderBy('sort_order')->get(['name', 'sort_order']);

    expect($types->pluck('name')->all())->toBe(['Tempo determinato', 'Tempo indeterminato', 'Apprendistato'])
        ->and($types->pluck('sort_order')->all())->toBe([1, 2, 3]);
});

it('0291 AC-005: a Tirocinio type that is already loaded is left untouched', function () {
    ContractType::factory()->create(['name' => 'Tirocinio', 'sort_order' => 4]);

    $this->seed(QualificaContractTypeSeeder::class);

    expect(ContractType::query()->count())->toBe(4)
        ->and(ContractType::query()->where('name', 'Tirocinio')->value('sort_order'))->toBe(4);
});

it('0290 AC-004: the HR office role holds the contract-types permissions', function () {
    $this->seed(QualificaRoleSeeder::class);
    $role = Role::findByName(HrOfficeRoleCatalogue::ROLE);

    foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
        expect($role->hasPermissionTo("contract-types.{$ability}"))->toBeTrue($ability);
    }
});

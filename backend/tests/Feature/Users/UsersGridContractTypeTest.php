<?php

use App\Models\ContractType;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\HrOfficeRoleCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function contractTypeGridActor(bool $contractsHidden): User
{
    $permissions = ['users.viewAny', 'users.view'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('contract-type-'.Str::random(8), 'web');
    $role->givePermissionTo($permissions);

    if ($contractsHidden) {
        foreach (HrOfficeRoleCatalogue::SENSITIVE_FIELDS['users'] as $field) {
            $role->fieldPermissions()->create(['resource' => 'users', 'field' => $field, 'visible' => false, 'editable' => false, 'required' => false]);
        }
    }

    $actor = User::factory()->create();
    $actor->assignRole($role);

    return $actor;
}

/**
 * @return array{0: User, 1: User, 2: User}
 */
function contractTypeGridUsers(): array
{
    $permanent = ContractType::factory()->create(['name' => 'Permanent']);
    $fixed = ContractType::factory()->create(['name' => 'Fixed term']);

    return [
        User::factory()->withEmployment(fn ($f) => $f->state(['contract_type_id' => $permanent->id]))->create(['name' => 'Perm User']),
        User::factory()->withEmployment(fn ($f) => $f->state(['contract_type_id' => $fixed->id]))->create(['name' => 'Fixed User']),
        User::factory()->withEmployment(fn ($f) => $f->state(['contract_type_id' => null]))->create(['name' => 'Blank User']),
    ];
}

function contractTypeGridRows(array $overrides = []): array
{
    return test()->postJson('/api/tables/users/rows', array_merge(['startRow' => 0, 'endRow' => 50], $overrides))
        ->assertOk()->json('items');
}

it('0290 AC-009: the cell carries the current contract type name', function () {
    contractTypeGridUsers();
    Sanctum::actingAs(contractTypeGridActor(false));

    $byName = collect(contractTypeGridRows())->pluck('contract_type', 'name');

    expect($byName['Perm User'])->toBe('Permanent')
        ->and($byName['Fixed User'])->toBe('Fixed term')
        ->and($byName['Blank User'])->toBeNull();
});

it('0290 AC-009: the set filter matches by name and by blank', function () {
    contractTypeGridUsers();
    Sanctum::actingAs(contractTypeGridActor(false));

    $named = collect(contractTypeGridRows(['filterModel' => ['contract_type' => ['values' => ['Permanent']]]]))->pluck('name');
    $blank = collect(contractTypeGridRows(['filterModel' => ['contract_type' => ['values' => [null]]]]))->pluck('name');

    expect($named->all())->toBe(['Perm User'])
        ->and($blank)->toContain('Blank User')->not->toContain('Perm User', 'Fixed User');
});

it('0290 AC-009: sorts by the contract type name', function () {
    contractTypeGridUsers();
    Sanctum::actingAs(contractTypeGridActor(false));

    $values = collect(contractTypeGridRows(['sortModel' => [['colId' => 'contract_type', 'sort' => 'asc']]]))
        ->pluck('contract_type')->filter()->values()->all();

    expect($values)->toBe(['Fixed term', 'Permanent']);
});

it('0290 AC-009: distinct values list the contract type names in use plus the blank entry', function () {
    contractTypeGridUsers();
    ContractType::factory()->create(['name' => 'Unused internship']);
    Sanctum::actingAs(contractTypeGridActor(false));

    $values = $this->postJson('/api/tables/users/values', ['columnId' => 'contract_type'])->assertOk()->json('data.values');

    expect($values)->toContain('Permanent', 'Fixed term', null)
        ->and($values)->not->toContain('Unused internship');
});

it('0290 AC-009: an actor with employment.contracts hidden loses the column, filter and value', function () {
    contractTypeGridUsers();
    Sanctum::actingAs(contractTypeGridActor(true));

    $columns = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'))->pluck('id');

    expect($columns)->not->toContain('contract_type')
        ->and(contractTypeGridRows()[0])->not->toHaveKey('contract_type');
    $this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 50, 'filterModel' => ['contract_type' => ['values' => ['Permanent']]],
    ])->assertUnprocessable();

    Sanctum::actingAs(contractTypeGridActor(false));
    $columns = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'))->pluck('id');

    expect($columns)->toContain('contract_type');
});

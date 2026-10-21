<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\QualificaCatalog\HrOfficeRoleCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function workingTimeGridActor(bool $contractsHidden): User
{
    $permissions = ['users.viewAny', 'users.view'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('working-time-'.Str::random(8), 'web');
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

function workingTimeGridUsers(): void
{
    User::factory()->withEmployment(fn ($f) => $f->state(['relationship_type' => 'employee', 'working_time' => 'full_time', 'contract_expires_on' => '2027-03-01']))->create(['name' => 'Full User']);
    User::factory()->withEmployment(fn ($f) => $f->state(['relationship_type' => 'intern', 'working_time' => 'part_time', 'contract_expires_on' => '2026-12-31']))->create(['name' => 'Part User']);
    User::factory()->withEmployment(fn ($f) => $f->state(['relationship_type' => 'other', 'working_time' => null, 'contract_expires_on' => null]))->create(['name' => 'Blank User']);
}

function workingTimeGridRows(array $overrides = []): array
{
    return test()->postJson('/api/tables/users/rows', array_merge(['startRow' => 0, 'endRow' => 50], $overrides))
        ->assertOk()->json('items');
}

it('0291 AC-006: the cells carry the current working time and expiry', function () {
    workingTimeGridUsers();
    Sanctum::actingAs(workingTimeGridActor(false));

    $rows = collect(workingTimeGridRows())->keyBy('name');

    expect($rows['Full User']['working_time'])->toBe('full_time')
        ->and(substr($rows['Full User']['contract_expires_on'], 0, 10))->toBe('2027-03-01')
        ->and($rows['Part User']['working_time'])->toBe('part_time')
        ->and($rows['Blank User']['working_time'])->toBeNull()
        ->and($rows['Blank User']['contract_expires_on'])->toBeNull();
});

it('0291 AC-006: the working time set filter matches by value and by blank, and sorts', function () {
    workingTimeGridUsers();
    Sanctum::actingAs(workingTimeGridActor(false));

    $part = collect(workingTimeGridRows(['filterModel' => ['working_time' => ['values' => ['part_time']]]]))->pluck('name');
    $blank = collect(workingTimeGridRows(['filterModel' => ['working_time' => ['values' => [null]]]]))->pluck('name');
    $sorted = collect(workingTimeGridRows(['sortModel' => [['colId' => 'working_time', 'sort' => 'asc']]]))
        ->pluck('working_time')->filter()->values()->all();

    expect($part->all())->toBe(['Part User'])
        ->and($blank)->toContain('Blank User')->not->toContain('Full User', 'Part User')
        ->and($sorted)->toBe(['full_time', 'part_time']);

    $values = $this->postJson('/api/tables/users/values', ['columnId' => 'working_time'])->assertOk()->json('data.values');
    expect($values)->toContain('full_time', 'part_time');
});

it('0291 AC-006: the expiry date filter and sort work, and relationship_type accepts intern', function () {
    workingTimeGridUsers();
    Sanctum::actingAs(workingTimeGridActor(false));

    $filtered = collect(workingTimeGridRows(['filterModel' => ['contract_expires_on' => [
        'filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2027-01-01 00:00:00',
    ]]]))->pluck('name');
    $sorted = collect(workingTimeGridRows(['sortModel' => [['colId' => 'contract_expires_on', 'sort' => 'asc']]]))
        ->pluck('contract_expires_on')->filter()->map(fn (string $date): string => substr($date, 0, 10))->values()->all();
    $interns = collect(workingTimeGridRows(['filterModel' => ['relationship_type' => ['values' => ['intern']]]]))->pluck('name');

    expect($filtered->all())->toBe(['Full User'])
        ->and($sorted)->toBe(['2026-12-31', '2027-03-01'])
        ->and($interns->all())->toBe(['Part User']);
});

it('0291 AC-006: the two columns follow contract_type in the catalogue', function () {
    Sanctum::actingAs(workingTimeGridActor(false));

    $columns = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'))->pluck('id')->values();
    $position = $columns->search('contract_type');

    expect($columns[$position + 1])->toBe('working_time')
        ->and($columns[$position + 2])->toBe('contract_expires_on');
});

it('0291 AC-006: an actor with employment.contracts hidden loses both columns and values', function () {
    workingTimeGridUsers();
    Sanctum::actingAs(workingTimeGridActor(true));

    $columns = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'))->pluck('id');
    $row = workingTimeGridRows()[0];

    expect($columns)->not->toContain('working_time', 'contract_expires_on')
        ->and($row)->not->toHaveKey('working_time')->not->toHaveKey('contract_expires_on');
});

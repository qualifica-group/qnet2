<?php

use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * The derived `registry_type` column (person vs company, personalData.type)
 * that drives the Anagrafiche list type tabs.
 */
function registryTypeViewer(): User
{
    Permission::findOrCreate('registries.viewAny');

    $user = User::factory()->create();
    $user->givePermissionTo('registries.viewAny');

    return $user;
}

function registryWithCardType(string $name, string $type): Registry
{
    $registry = Registry::factory()->create(['name' => $name]);
    PersonalData::factory()->{$type}()->for($registry, 'personable')->create();

    return $registry;
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<int, string>
 */
function registryTypeRowNames(array $payload): array
{
    return collect(test()->postJson('/api/tables/registries/rows', ['startRow' => 0, 'endRow' => 25, ...$payload])
        ->assertOk()
        ->json('items'))
        ->pluck('name')
        ->all();
}

it('declares registry_type as a sortable set-filter badge localized from personal_data_type', function () {
    Sanctum::actingAs(registryTypeViewer());

    $data = $this->getJson('/api/tables/registries/columns')->assertOk()->json('data');
    $column = collect($data['columns'])->firstWhere('id', 'registry_type');

    expect($column)->not->toBeNull()
        ->and($column['type'])->toBe('badge')
        ->and($column['sortable'])->toBeTrue()
        ->and($column['filterType'])->toBe('set')
        ->and($column['enumKey'])->toBe('personal_data_type')
        ->and(collect($column['badges'])->pluck('value')->all())->toBe(['individual', 'company']);
});

it('exposes the card type on each row, null without a card', function () {
    registryWithCardType('Mario Rossi', 'individual');
    registryWithCardType('Acme Spa', 'company');
    Registry::factory()->create(['name' => 'No Card']);
    Sanctum::actingAs(registryTypeViewer());

    $rows = collect($this->postJson('/api/tables/registries/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->json('items'))
        ->keyBy('name');

    expect($rows['Mario Rossi']['registry_type'])->toBe('individual')
        ->and($rows['Acme Spa']['registry_type'])->toBe('company')
        ->and($rows['No Card']['registry_type'])->toBeNull();
});

it('filters rows by type: individuals, companies, blank', function () {
    registryWithCardType('Mario Rossi', 'individual');
    registryWithCardType('Acme Spa', 'company');
    Registry::factory()->create(['name' => 'No Card']);
    Sanctum::actingAs(registryTypeViewer());

    $filter = fn (array $values): array => ['filterModel' => ['registry_type' => ['filterType' => 'set', 'values' => $values]]];

    expect(registryTypeRowNames($filter(['individual'])))->toBe(['Mario Rossi'])
        ->and(registryTypeRowNames($filter(['company'])))->toBe(['Acme Spa'])
        ->and(registryTypeRowNames($filter([null])))->toBe(['No Card']);
});

it('ignores values outside the enum instead of matching nothing', function () {
    registryWithCardType('Mario Rossi', 'individual');
    registryWithCardType('Acme Spa', 'company');
    Sanctum::actingAs(registryTypeViewer());

    $names = registryTypeRowNames(['filterModel' => ['registry_type' => ['filterType' => 'set', 'values' => ['bogus']]]]);

    expect($names)->toEqualCanonicalizing(['Mario Rossi', 'Acme Spa']);
});

it('sorts rows by type via a correlated subquery', function () {
    registryWithCardType('Mario Rossi', 'individual');
    registryWithCardType('Acme Spa', 'company');
    Sanctum::actingAs(registryTypeViewer());

    $sort = fn (string $direction): array => ['sortModel' => [['colId' => 'registry_type', 'sort' => $direction]]];

    expect(registryTypeRowNames($sort('asc')))->toBe(['Acme Spa', 'Mario Rossi'])
        ->and(registryTypeRowNames($sort('desc')))->toBe(['Mario Rossi', 'Acme Spa']);
});

it('resolves the distinct type values via /values with the blank entry', function () {
    Sanctum::actingAs(registryTypeViewer());

    $values = $this->postJson('/api/tables/registries/values', ['columnId' => 'registry_type', 'limit' => 25])
        ->assertOk()
        ->json('data.values');

    expect($values)->toBe([null, 'individual', 'company']);
});

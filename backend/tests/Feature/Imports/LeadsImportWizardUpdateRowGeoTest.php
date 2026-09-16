<?php

use App\Enums\ImportRowStatus;
use App\Enums\ImportStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 */
function updateRowGeoActorWith(array $abilities): User
{
    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $user = User::factory()->create();

    foreach ($abilities as $ability) {
        $user->givePermissionTo("leads.{$ability}");
    }

    if (in_array('import', $abilities, true)) {
        grantImportRunsPermissions($user, ['update']);
    }

    return $user;
}

/**
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function updateRowGeoChain(): array
{
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->create(['name' => 'Lombardy', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milan', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milan', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

function updateRowGeoRun(User $actor): ImportRun
{
    return ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'leads',
        'status' => ImportStatus::Reviewing,
        'column_mapping' => ['Nome' => 'first_name', 'Cognome' => 'last_name', 'Paese' => 'country', 'Regione' => 'region', 'Provincia' => 'province', 'Citta' => 'city'],
        'dedup_strategy' => 'create_new',
    ]);
}

/**
 * A row staged with a geo warning: the free-text geo fields did not resolve
 * unambiguously (spec 0033 AC-005), mirroring what GeoRecognizer would have
 * left behind.
 */
function warningGeoRow(ImportRun $run): ImportRunRow
{
    return ImportRunRow::factory()->create([
        'import_run_id' => $run->id,
        'row_number' => 1,
        'mapped_values' => [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'country' => 'Italy',
            'region' => 'Lombardyy',
            'province' => 'Milan',
            'city' => 'Milan',
            'country_id' => null,
            'state_id' => null,
            'province_id' => null,
            'city_id' => null,
        ],
        'status' => ImportRowStatus::Warning,
        'messages' => ['Region "Lombardyy" not found or ambiguous.'],
    ]);
}

// ---------------------------------------------------------------------------
// AC-001/002 — `geo` pin resolves ids to canonical names, clears the warning
// ---------------------------------------------------------------------------

it('AC-001: a coherent geo pin resolves canonical names + ids, clears the geo warning and revalidates to valid', function () {
    $actor = updateRowGeoActorWith(['import']);
    $geo = updateRowGeoChain();
    $run = updateRowGeoRun($actor);
    $row = warningGeoRow($run);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'geo' => [
            'country_id' => $geo['country']->id,
            'state_id' => $geo['state']->id,
            'province_id' => $geo['province']->id,
            'city_id' => $geo['city']->id,
        ],
    ])->assertOk();

    $response->assertJsonPath('data.row.values.country', 'Italy')
        ->assertJsonPath('data.row.values.region', 'Lombardy')
        ->assertJsonPath('data.row.values.province', 'Milan')
        ->assertJsonPath('data.row.values.city', 'Milan')
        ->assertJsonPath('data.row.values.country_id', $geo['country']->id)
        ->assertJsonPath('data.row.values.state_id', $geo['state']->id)
        ->assertJsonPath('data.row.values.province_id', $geo['province']->id)
        ->assertJsonPath('data.row.values.city_id', $geo['city']->id)
        ->assertJsonPath('data.row.status', 'valid')
        ->assertJsonPath('data.row.is_edited', true)
        ->assertJsonPath('data.row.messages', [])
        ->assertJsonPath('data.counts.warning_rows', 0)
        ->assertJsonPath('data.counts.valid_rows', 1);

    expect($row->fresh()->messages)->toBeNull();
});

it('AC-002: a partial geo pin (country+state) blanks province/city names and ids', function () {
    $actor = updateRowGeoActorWith(['import']);
    $geo = updateRowGeoChain();
    $run = updateRowGeoRun($actor);
    $row = warningGeoRow($run);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'geo' => [
            'country_id' => $geo['country']->id,
            'state_id' => $geo['state']->id,
            'province_id' => null,
            'city_id' => null,
        ],
    ])->assertOk();

    $response->assertJsonPath('data.row.values.country', 'Italy')
        ->assertJsonPath('data.row.values.region', 'Lombardy')
        ->assertJsonPath('data.row.values.province', '')
        ->assertJsonPath('data.row.values.city', '')
        ->assertJsonPath('data.row.values.province_id', null)
        ->assertJsonPath('data.row.values.city_id', null);
});

// ---------------------------------------------------------------------------
// AC-003/004/005 — validation: hierarchy, unknown ids/keys, missing body
// ---------------------------------------------------------------------------

it('AC-003: a city_id outside the declared province is a 422 and leaves the row untouched', function () {
    $actor = updateRowGeoActorWith(['import']);
    $geo = updateRowGeoChain();
    $otherProvince = Province::factory()->create(['name' => 'Bergamo', 'state_id' => $geo['state']->id, 'country_id' => $geo['country']->id]);
    $run = updateRowGeoRun($actor);
    $row = warningGeoRow($run);
    $before = $row->only(['mapped_values', 'status', 'messages', 'is_edited']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'geo' => [
            'country_id' => $geo['country']->id,
            'state_id' => $geo['state']->id,
            'province_id' => $otherProvince->id,
            'city_id' => $geo['city']->id,
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('geo.city_id');

    expect($row->fresh()->only(['mapped_values', 'status', 'messages', 'is_edited']))->toBe($before);
});

it('AC-003: a state_id belonging to another country is a 422', function () {
    $actor = updateRowGeoActorWith(['import']);
    $geo = updateRowGeoChain();
    $otherCountry = Country::factory()->create(['name' => 'France']);
    $run = updateRowGeoRun($actor);
    $row = warningGeoRow($run);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'geo' => [
            'country_id' => $otherCountry->id,
            'state_id' => $geo['state']->id,
            'province_id' => null,
            'city_id' => null,
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('geo.state_id');
});

it('AC-004: an inexistent id in geo is a 422', function () {
    $actor = updateRowGeoActorWith(['import']);
    $run = updateRowGeoRun($actor);
    $row = warningGeoRow($run);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'geo' => ['country_id' => 999999, 'state_id' => null, 'province_id' => null, 'city_id' => null],
    ])->assertStatus(422)->assertJsonValidationErrors('geo.country_id');
});

it('AC-004: an unknown key inside geo is a 422', function () {
    $actor = updateRowGeoActorWith(['import']);
    $geo = updateRowGeoChain();
    $run = updateRowGeoRun($actor);
    $row = warningGeoRow($run);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'geo' => ['country_id' => $geo['country']->id, 'foo' => 'bar'],
    ])->assertStatus(422)->assertJsonValidationErrors('geo.foo');
});

it('AC-005: neither values nor geo in the body is a 422', function () {
    $actor = updateRowGeoActorWith(['import']);
    $run = updateRowGeoRun($actor);
    $row = warningGeoRow($run);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [])
        ->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-006/008 — status guard + authz, extended to the geo body
// ---------------------------------------------------------------------------

it('AC-006: a geo PATCH on a non-reviewing run is a 422', function () {
    $actor = updateRowGeoActorWith(['import']);
    $geo = updateRowGeoChain();
    $run = updateRowGeoRun($actor);
    $run->update(['status' => ImportStatus::Completed]);
    $row = warningGeoRow($run);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'geo' => ['country_id' => $geo['country']->id, 'state_id' => null, 'province_id' => null, 'city_id' => null],
    ])->assertStatus(422);
});

it('AC-008: 403 without leads.import on a geo PATCH', function () {
    $actor = updateRowGeoActorWith([]);
    $geo = updateRowGeoChain();
    $run = ImportRun::factory()->create(['user_id' => $actor->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'geo' => ['country_id' => $geo['country']->id, 'state_id' => null, 'province_id' => null, 'city_id' => null],
    ])->assertForbidden();
});

it('AC-008: a geo PATCH on a run started by another user succeeds (runs are shared)', function () {
    $actor = updateRowGeoActorWith(['import']);
    $geo = updateRowGeoChain();
    $otherUser = User::factory()->create();
    $run = ImportRun::factory()->create(['user_id' => $otherUser->id, 'resource' => 'leads', 'status' => ImportStatus::Reviewing]);
    $row = ImportRunRow::factory()->create(['import_run_id' => $run->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'geo' => ['country_id' => $geo['country']->id, 'state_id' => null, 'province_id' => null, 'city_id' => null],
    ])->assertOk();
});

// ---------------------------------------------------------------------------
// AC-009 — a geo pin holds stable through a later non-geo `values` PATCH
// ---------------------------------------------------------------------------

it('AC-009: a later values-only PATCH on a non-geo field does not reintroduce the geo warning', function () {
    $actor = updateRowGeoActorWith(['import']);
    $geo = updateRowGeoChain();
    $run = updateRowGeoRun($actor);
    $row = warningGeoRow($run);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'geo' => [
            'country_id' => $geo['country']->id,
            'state_id' => $geo['state']->id,
            'province_id' => $geo['province']->id,
            'city_id' => $geo['city']->id,
        ],
    ])->assertOk();

    $response = $this->patchJson("/api/imports/leads/{$run->id}/rows/{$row->id}", [
        'values' => ['first_name' => 'Luigi'],
    ])->assertOk();

    $response->assertJsonPath('data.row.status', 'valid')
        ->assertJsonPath('data.row.messages', [])
        ->assertJsonPath('data.row.values.country_id', $geo['country']->id)
        ->assertJsonPath('data.row.values.state_id', $geo['state']->id)
        ->assertJsonPath('data.row.values.province_id', $geo['province']->id)
        ->assertJsonPath('data.row.values.city_id', $geo['city']->id);
});

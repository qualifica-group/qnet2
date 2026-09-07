<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// The real backend .env reaches the test process, so DEFAULT_COUNTRY_ISO2=IT
// would silently narrow the parentless city-first lookup below (and
// CountryFactory can mint the code "IT" by chance). International mode is made
// explicit here; national mode has its own block at the bottom of the file.
beforeEach(function () {
    config()->set('geo.default_country_iso2', null);
});

// ---------------------------------------------------------------------------
// auth — every geo endpoint is gated by auth:sanctum
// ---------------------------------------------------------------------------

it('countries: 401 without authentication', function () {
    $this->getJson('/api/countries')->assertUnauthorized();
});

it('states: 401 without authentication', function () {
    $this->getJson('/api/states?country_id=1')->assertUnauthorized();
});

it('provinces: 401 without authentication', function () {
    $this->getJson('/api/provinces?state_id=1')->assertUnauthorized();
});

it('cities: 401 without authentication', function () {
    $this->getJson('/api/cities?state_id=1')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// countries — GET /api/countries
// ---------------------------------------------------------------------------

it('countries: 200 returns every country ordered by name with the resource shape', function () {
    Sanctum::actingAs(User::factory()->create());

    Country::factory()->create(['name' => 'Zambia', 'iso2' => 'ZM']);
    Country::factory()->create(['name' => 'Albania', 'iso2' => 'AL']);

    $response = $this->getJson('/api/countries')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Albania')
        ->assertJsonPath('data.1.name', 'Zambia')
        ->assertJsonStructure(['data' => [['id', 'name', 'iso2']]]);

    // Only the three allowlisted keys are exposed.
    expect(array_keys($response->json('data.0')))->toEqualCanonicalizing(['id', 'name', 'iso2']);
});

// ---------------------------------------------------------------------------
// states — GET /api/states?country_id={id}
// ---------------------------------------------------------------------------

it('states: 422 when country_id is missing', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/states')
        ->assertStatus(422)
        ->assertJsonValidationErrors('country_id');
});

it('states: 422 when country_id does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/states?country_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('country_id');
});

it('states: 200 returns only the country states, ordered by name, with the resource shape', function () {
    Sanctum::actingAs(User::factory()->create());

    $country = Country::factory()->create();
    $other = Country::factory()->create();

    State::factory()->for($country, 'country')->create(['name' => 'Veneto']);
    State::factory()->for($country, 'country')->create(['name' => 'Abruzzo']);
    State::factory()->for($other, 'country')->create(['name' => 'Bavaria']);

    $this->getJson("/api/states?country_id={$country->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Abruzzo')
        ->assertJsonPath('data.1.name', 'Veneto')
        ->assertJsonPath('data.0.country_id', $country->id)
        ->assertJsonStructure(['data' => [['id', 'name', 'country_id']]]);
});

// ---------------------------------------------------------------------------
// provinces — GET /api/provinces?state_id={id}
// ---------------------------------------------------------------------------

it('provinces: 422 when state_id is missing', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/provinces')
        ->assertStatus(422)
        ->assertJsonValidationErrors('state_id');
});

it('provinces: 422 when state_id does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/provinces?state_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('state_id');
});

it('provinces: 200 returns only the state provinces, ordered by name, with the resource shape', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    $other = State::factory()->create();

    Province::factory()->forState($state)->create(['name' => 'Naples']);
    Province::factory()->forState($state)->create(['name' => 'Caserta']);
    Province::factory()->forState($other)->create(['name' => 'Milan']);

    $response = $this->getJson("/api/provinces?state_id={$state->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Caserta')
        ->assertJsonPath('data.1.name', 'Napoli')
        ->assertJsonPath('data.0.state_id', $state->id)
        ->assertJsonStructure(['data' => [['id', 'name', 'state_id']]]);

    expect(array_keys($response->json('data.0')))->toEqualCanonicalizing(['id', 'name', 'state_id']);
});

// ---------------------------------------------------------------------------
// cities — GET /api/cities?state_id={id}|province_id={id}&search={q}
// ---------------------------------------------------------------------------

it('cities: 422 when state_id is missing', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/cities')
        ->assertStatus(422)
        ->assertJsonValidationErrors('state_id');
});

it('cities: 422 when state_id does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/cities?state_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('state_id');
});

it('cities: 200 returns only the state cities, ordered by name, with the resource shape', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    $other = State::factory()->create();

    City::factory()->forState($state)->create(['name' => 'Verona']);
    City::factory()->forState($state)->create(['name' => 'Ancona']);
    City::factory()->forState($other)->create(['name' => 'Munich']);

    $this->getJson("/api/cities?state_id={$state->id}")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Ancona')
        ->assertJsonPath('data.1.name', 'Verona')
        ->assertJsonPath('data.0.state_id', $state->id)
        ->assertJsonPath('data.0.country_id', $state->country_id)
        ->assertJsonStructure(['data' => [['id', 'name', 'country_id', 'state_id', 'province_id']]]);
});

it('cities: search alone (no parent) does an unscoped city-first lookup across states', function () {
    Sanctum::actingAs(User::factory()->create());

    $one = State::factory()->create();
    $two = State::factory()->create();
    City::factory()->forState($one)->create(['name' => 'Rome']);
    City::factory()->forState($two)->create(['name' => 'Rome']);
    City::factory()->forState($one)->create(['name' => 'Milan']);

    // Stored "Rome" is served through the Italian localizer as "Roma".
    $this->getJson('/api/cities?search=Rom')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Roma')
        ->assertJsonPath('data.1.name', 'Roma');
});

it('cities: search filters by a name LIKE prefix', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    City::factory()->forState($state)->create(['name' => 'Verona']);
    City::factory()->forState($state)->create(['name' => 'Venice']);
    City::factory()->forState($state)->create(['name' => 'Milan']);

    $this->getJson("/api/cities?state_id={$state->id}&search=Ve")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Venezia')
        ->assertJsonPath('data.1.name', 'Verona');
});

it('cities: a search typed in Italian reaches the rows stored anglicized', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    City::factory()->forState($state)->create(['name' => 'Naples']);
    City::factory()->forState($state)->create(['name' => 'Milan']);

    // The user types the Italian name they see in the select; the row is stored
    // as "Naples" by the reference dataset.
    $this->getJson("/api/cities?state_id={$state->id}&search=napoli")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Napoli');
});

it('cities: an Italian search still matches rows already stored in Italian', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    // Most Italian comuni are already native in the dataset: the English-alias
    // branch must widen the search, never replace the plain prefix match.
    City::factory()->forState($state)->create(['name' => 'Napoli']);
    City::factory()->forState($state)->create(['name' => 'Naples']);
    City::factory()->forState($state)->create(['name' => 'Aversa']);

    $this->getJson("/api/cities?state_id={$state->id}&search=napoli")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Napoli')
        ->assertJsonPath('data.1.name', 'Napoli');
});

it('cities: the Italian alias stays a PREFIX match, not a contains match', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    City::factory()->forState($state)->create(['name' => 'Naples']);

    // "poli" is a substring of "Napoli" but not a prefix: the select is a
    // prefix lookup, so the alias branch must not smuggle in a contains match.
    $this->getJson("/api/cities?state_id={$state->id}&search=poli")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('cities: the aliased row ranks first, ahead of same-prefix Italian names', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    // Sorted on the ENGLISH column "Rome" falls behind every "Romagn...", so
    // without the ranking it is pushed past the 50-row cap and "roma" never
    // surfaces Roma on the first page.
    City::factory()->forState($state)->create(['name' => 'Rome']);
    foreach (range(0, 59) as $index) {
        City::factory()->forState($state)->create([
            'name' => sprintf('Romagnano-%03d', $index),
        ]);
    }

    $this->getJson("/api/cities?state_id={$state->id}&search=roma")
        ->assertOk()
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('data.0.name', 'Roma');
});

it('cities: the English name keeps working as a search term', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    City::factory()->forState($state)->create(['name' => 'Naples']);

    $this->getJson("/api/cities?state_id={$state->id}&search=naples")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Napoli');
});

it('cities: caps the result set at 50', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    City::factory()->forState($state)->count(60)->create();

    $this->getJson("/api/cities?state_id={$state->id}")
        ->assertOk()
        ->assertJsonCount(50, 'data');
});

it('cities: offset pages past the first 50 for infinite scroll', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    // Names sort deterministically as City-000 .. City-059 so the page boundary
    // is predictable regardless of insertion order.
    foreach (range(0, 59) as $index) {
        City::factory()->forState($state)->create([
            'name' => sprintf('City-%03d', $index),
        ]);
    }

    $this->getJson("/api/cities?state_id={$state->id}&offset=50")
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('data.0.name', 'City-050')
        ->assertJsonPath('data.9.name', 'City-059');
});

it('cities: 422 when province_id does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/cities?province_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('province_id');
});

it('cities: filters by province_id (the finest level), winning over state_id', function () {
    Sanctum::actingAs(User::factory()->create());

    $state = State::factory()->create();
    $province = Province::factory()->forState($state)->create();
    $otherProvince = Province::factory()->forState($state)->create();

    City::factory()->forProvince($province)->create(['name' => 'Grumo Nevano']);
    City::factory()->forProvince($otherProvince)->create(['name' => 'Aversa']);
    // A city of the same state but with no province must NOT appear when
    // filtering by province.
    City::factory()->forState($state)->create(['name' => 'Orphan City']);

    $this->getJson("/api/cities?province_id={$province->id}&state_id={$state->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Grumo Nevano')
        ->assertJsonPath('data.0.province_id', $province->id)
        ->assertJsonPath('data.0.state_id', $state->id)
        ->assertJsonStructure(['data' => [['id', 'name', 'state_id', 'province_id']]]);
});

// ---------------------------------------------------------------------------
// national mode — only the parentless city-first branch is narrowed; the
// cascade keeps every country reachable
// ---------------------------------------------------------------------------

it('cities: national mode bounds the city-first search to the configured country', function () {
    Sanctum::actingAs(User::factory()->create());
    $italy = Country::factory()->create(['iso2' => 'IT']);
    $italianState = State::factory()->for($italy, 'country')->create();
    City::factory()->forState($italianState)->create(['name' => 'Verona']);
    City::factory()->forState(State::factory()->create())->create(['name' => 'Vernon']);
    config()->set('geo.default_country_iso2', 'IT');

    $this->getJson('/api/cities?search=Ver')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Verona');
});

it('cities: national mode leaves a state-scoped lookup alone (foreign cascade still works)', function () {
    Sanctum::actingAs(User::factory()->create());
    Country::factory()->create(['iso2' => 'IT']);
    $foreignState = State::factory()->create();
    City::factory()->forState($foreignState)->create(['name' => 'Vernon']);
    config()->set('geo.default_country_iso2', 'IT');

    $this->getJson("/api/cities?state_id={$foreignState->id}&search=Ver")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Vernon');
});

it('cities: an unresolvable configured code degrades to the worldwide city-first lookup', function () {
    Sanctum::actingAs(User::factory()->create());
    City::factory()->forState(State::factory()->create())->create(['name' => 'Verona']);
    City::factory()->forState(State::factory()->create())->create(['name' => 'Vernon']);
    config()->set('geo.default_country_iso2', 'ZZ');

    $this->getJson('/api/cities?search=Ver')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

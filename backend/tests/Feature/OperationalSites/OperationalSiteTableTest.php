<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\OperationalSite;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithSiteAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithSiteAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("operational-sites.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("operational-sites.{$ability}");
        }

        return $user;
    }
}

/**
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function siteTableGeoChain(string $cityName, string $provinceName, string $stateName, string $countryName): array
{
    $country = Country::factory()->create(['name' => $countryName]);
    $state = State::factory()->create(['name' => $stateName, 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => $provinceName, 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => $cityName, 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

// ---------------------------------------------------------------------------
// AC-003 — columns config
// ---------------------------------------------------------------------------

it('returns the 9 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = userWithSiteAbilities([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/operational-sites/columns')->assertForbidden();

    $actor = userWithSiteAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/operational-sites/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('operational-sites')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['defaultPagination']['limit'])->toBe(25)
        ->and($data['searchable'])->toBe(['alias', 'city', 'street']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'alias', 'city', 'street', 'postal_code', 'province', 'region', 'is_active', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['id']['visible'])->toBeFalse()
        ->and($columns['id']['filterType'])->toBe('number')
        ->and($columns['alias']['visible'])->toBeTrue()
        ->and($columns['alias']['filterType'])->toBe('text')
        ->and($columns['alias']['hasFilterValues'])->toBeFalse()
        ->and($columns['city']['visible'])->toBeTrue()
        ->and($columns['city']['filterType'])->toBe('set')
        ->and($columns['street']['filterType'])->toBe('text')
        ->and($columns['street']['hasFilterValues'])->toBeFalse()
        ->and($columns['postal_code']['filterType'])->toBe('text')
        ->and($columns['postal_code']['hasFilterValues'])->toBeFalse()
        ->and($columns['province']['filterType'])->toBe('set')
        ->and($columns['region']['filterType'])->toBe('set')
        ->and($columns['is_active']['visible'])->toBeTrue()
        ->and($columns['is_active']['filterType'])->toBe('boolean')
        ->and($columns['created_at']['filterType'])->toBe('date');
});

it('hides action keys the user has no permission for', function () {
    $actor = userWithSiteAbilities(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/operational-sites/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toContain('view')
        ->and($actionKeys)->not->toContain('edit')
        ->and($actionKeys)->not->toContain('delete');
});

// ---------------------------------------------------------------------------
// AC-004 — rows shape (derived geo/street/postal_code), actions, no N+1
// ---------------------------------------------------------------------------

it('rows expose the derived geo/street/postal_code fields and per-row actions', function () {
    $actor = userWithSiteAbilities(['viewAny', 'view', 'update', 'delete']);
    $geo = siteTableGeoChain('Milano', 'Milano', 'Lombardia', 'Italia');
    $target = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($geo['city'])->for($target, 'addressable')->create([
        'line1' => 'Via Test 1',
        'postal_code' => '20100',
    ]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/operational-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
    ])->assertOk();

    $row = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($row)->not->toBeNull()
        ->and($row['city'])->toBe('Milano')
        ->and($row['street'])->toBe('Via Test 1')
        ->and($row['province'])->toBe('Milano')
        ->and($row['region'])->toBe('Lombardia')
        ->and($row['postal_code'])->toBe('20100')
        ->and($row['is_active'])->toBeTrue()
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

it('a site with no address has null derived fields', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $target = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/operational-sites/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($row['city'])->toBeNull()
        ->and($row['street'])->toBeNull()
        ->and($row['postal_code'])->toBeNull();
});

it('rows resolve the derived address fields with a bounded query count (no N+1)', function () {
    $actor = userWithSiteAbilities(['viewAny']);

    foreach (range(1, 5) as $i) {
        $geo = siteTableGeoChain("City{$i}", "Province{$i}", "Region{$i}", "Country{$i}");
        $site = OperationalSite::factory()->create();
        Address::factory()->primary()->forCity($geo['city'])->for($site, 'addressable')->create();
    }

    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $this->postJson('/api/tables/operational-sites/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->assertJsonCount(5, 'items');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // A fixed, small number of queries regardless of row count, never one per row.
    expect($queryCount)->toBeLessThan(10);
});

// ---------------------------------------------------------------------------
// AC-006 — derived set filter + derived sort
// ---------------------------------------------------------------------------

it('filters rows by the derived province set filter (whereHas by name)', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $milano = siteTableGeoChain('Milano', 'Milano', 'Lombardia', 'Italia');
    $roma = siteTableGeoChain('Roma', 'Roma', 'Lazio', 'Italia');
    $siteA = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($milano['city'])->for($siteA, 'addressable')->create();
    $siteB = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($roma['city'])->for($siteB, 'addressable')->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/operational-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['province' => ['filterType' => 'set', 'values' => ['Milano']]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$siteA->id]);
});

it('filters rows by the derived street text filter', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $siteA = OperationalSite::factory()->create();
    Address::factory()->primary()->for($siteA, 'addressable')->create(['line1' => 'Via Roma 20100']);
    $siteB = OperationalSite::factory()->create();
    Address::factory()->primary()->for($siteB, 'addressable')->create(['line1' => 'Via Torino 1']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/operational-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['street' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'Roma']],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$siteA->id]);
});

it('sorts rows by the derived city name via a correlated subquery', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $zed = siteTableGeoChain('Zeta City', 'Zeta Province', 'Zeta Region', 'Zeta Country');
    $amy = siteTableGeoChain('Amy City', 'Amy Province', 'Amy Region', 'Amy Country');
    $siteZ = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($zed['city'])->for($siteZ, 'addressable')->create();
    $siteA = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($amy['city'])->for($siteA, 'addressable')->create();
    Sanctum::actingAs($actor);

    $ids = $this->postJson('/api/tables/operational-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'city', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.id');

    expect(array_search($siteA->id, $ids, true))->toBeLessThan(array_search($siteZ->id, $ids, true));
});

it('sorts rows by the derived street value via a correlated subquery', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $siteZ = OperationalSite::factory()->create();
    Address::factory()->primary()->for($siteZ, 'addressable')->create(['line1' => 'Zeta Street']);
    $siteA = OperationalSite::factory()->create();
    Address::factory()->primary()->for($siteA, 'addressable')->create(['line1' => 'Alpha Street']);
    Sanctum::actingAs($actor);

    $ids = $this->postJson('/api/tables/operational-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'street', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.id');

    expect(array_search($siteA->id, $ids, true))->toBeLessThan(array_search($siteZ->id, $ids, true));
});

// ---------------------------------------------------------------------------
// AC-007 — global quick-search on the derived city/street columns
// ---------------------------------------------------------------------------

it('matches the derived quick-search on city (comune)', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $milano = siteTableGeoChain('Millepini', 'Prov', 'Reg', 'Country');
    $roma = siteTableGeoChain('Roma', 'Prov2', 'Reg2', 'Country2');
    $siteA = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($milano['city'])->for($siteA, 'addressable')->create();
    $siteB = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($roma['city'])->for($siteB, 'addressable')->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/operational-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'search' => 'millepini',
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$siteA->id]);
});

it('matches the derived city quick-search typed in Italian against an English-stored name', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    // The reference dataset stores this city anglicized; the grid shows "Napoli".
    $naples = siteTableGeoChain('Naples', 'Prov', 'Reg', 'Country');
    $rome = siteTableGeoChain('Roma', 'Prov2', 'Reg2', 'Country2');
    $siteA = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($naples['city'])->for($siteA, 'addressable')->create();
    $siteB = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($rome['city'])->for($siteB, 'addressable')->create();
    Sanctum::actingAs($actor);

    $italian = collect($this->postJson('/api/tables/operational-sites/rows', [
        'startRow' => 0, 'endRow' => 25, 'search' => 'napoli',
    ])->assertOk()->json('items'))->pluck('id');

    // Searching in English still works too (the plain LIKE is kept alongside).
    $english = collect($this->postJson('/api/tables/operational-sites/rows', [
        'startRow' => 0, 'endRow' => 25, 'search' => 'naples',
    ])->assertOk()->json('items'))->pluck('id');

    expect($italian->all())->toBe([$siteA->id])
        ->and($english->all())->toBe([$siteA->id]);
});

it('matches the derived quick-search on street (via)', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $siteA = OperationalSite::factory()->create();
    Address::factory()->primary()->for($siteA, 'addressable')->create(['line1' => 'Via Unica Corso']);
    $siteB = OperationalSite::factory()->create();
    Address::factory()->primary()->for($siteB, 'addressable')->create(['line1' => 'Corso Diverso']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/operational-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'search' => 'unica',
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$siteA->id]);
});

it('the derived quick-search matches either city OR street (OR combination)', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $geo = siteTableGeoChain('Needle Town', 'Prov', 'Reg', 'Country');
    $byCity = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($geo['city'])->for($byCity, 'addressable')->create(['line1' => 'Some Street']);
    $byStreet = OperationalSite::factory()->create();
    Address::factory()->primary()->for($byStreet, 'addressable')->create(['line1' => 'Needle Street']);
    $neither = OperationalSite::factory()->create();
    Address::factory()->primary()->for($neither, 'addressable')->create(['line1' => 'Other Street']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/operational-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'search' => 'needle',
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toEqualCanonicalizing([$byCity->id, $byStreet->id]);
});

// ---------------------------------------------------------------------------
// AC-005 — /values distinct values for a geo set column
// ---------------------------------------------------------------------------

it('resolves distinct province names via /values', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $milano = siteTableGeoChain('Milano', 'Milano', 'Lombardia', 'Italia');
    $site = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($milano['city'])->for($site, 'addressable')->create();
    OperationalSite::factory()->create(); // no address
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/operational-sites/values', ['columnId' => 'province'])->assertOk();

    // `null` first: the blank entry ("(Vuoti)") — the row with no address.
    expect($response->json('data.values'))->toBe([null, 'Milano']);
});

it('/values search narrows the distinct city names', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $milano = siteTableGeoChain('Milano', 'MilanoP', 'Lombardia', 'Italia');
    $roma = siteTableGeoChain('Roma', 'RomaP', 'Lazio', 'Italia');
    $siteA = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($milano['city'])->for($siteA, 'addressable')->create();
    $siteB = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($roma['city'])->for($siteB, 'addressable')->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/operational-sites/values', [
        'columnId' => 'city',
        'search' => 'mil',
    ])->assertOk();

    expect($response->json('data.values'))->toBe(['Milano']);
});

it('422 on the values endpoint when columnId is not filterable', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/operational-sites/values', ['columnId' => 'not-a-column'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});

it('short-circuits to an empty result for street (hasFilterValues:false, no discrete list)', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/operational-sites/values', ['columnId' => 'street'])->assertOk();

    expect($response->json('data.values'))->toBe([]);
});

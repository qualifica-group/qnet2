<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithCompanyAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithCompanyAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("companies.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("companies.{$ability}");
        }

        return $user;
    }
}

/**
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function companyTableGeoChain(string $cityName, string $provinceName, string $stateName, string $countryName): array
{
    $country = Country::factory()->create(['name' => $countryName]);
    $state = State::factory()->create(['name' => $stateName, 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => $provinceName, 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => $cityName, 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

// ---------------------------------------------------------------------------
// AC-009 — columns config
// ---------------------------------------------------------------------------

it('returns the 9 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = userWithCompanyAbilities([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/companies/columns')->assertForbidden();

    $actor = userWithCompanyAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/companies/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('companies')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['defaultPagination']['limit'])->toBe(25)
        ->and($data['searchable'])->toBe(['denomination', 'vat_number']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'denomination', 'vat_number', 'city', 'province', 'region', 'postal_code', 'country', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['denomination']['sortable'])->toBeTrue()
        ->and($columns['denomination']['filterType'])->toBe('text')
        ->and($columns['denomination']['visible'])->toBeTrue()
        ->and($columns['vat_number']['filterType'])->toBe('text')
        ->and($columns['city']['visible'])->toBeFalse()
        ->and($columns['city']['filterType'])->toBe('set')
        ->and($columns['province']['filterType'])->toBe('set')
        ->and($columns['region']['filterType'])->toBe('set')
        ->and($columns['country']['filterType'])->toBe('set')
        ->and($columns['postal_code']['visible'])->toBeTrue()
        ->and($columns['postal_code']['filterType'])->toBe('text')
        ->and($columns['postal_code']['hasFilterValues'])->toBeFalse()
        ->and($columns['created_at']['filterType'])->toBe('date');
});

it('hides action keys the user has no permission for', function () {
    $actor = userWithCompanyAbilities(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/companies/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toContain('view')
        ->and($actionKeys)->not->toContain('edit')
        ->and($actionKeys)->not->toContain('delete');
});

// ---------------------------------------------------------------------------
// AC-010 — rows shape (derived geo/postal_code), actions, sort/filter, no N+1
// ---------------------------------------------------------------------------

it('rows expose the derived geo/postal_code fields and per-row actions', function () {
    $actor = userWithCompanyAbilities(['viewAny', 'view', 'update', 'delete']);
    $geo = companyTableGeoChain('Milano', 'Milano', 'Lombardia', 'Italia');
    $target = Company::factory()->create(['denomination' => 'Finance Srl']);
    Address::factory()->primary()->forCity($geo['city'])->for($target, 'addressable')->create(['postal_code' => '20100']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0,
        'endRow' => 25,
    ])->assertOk();

    $row = collect($response->json('items'))->firstWhere('denomination', 'Finance Srl');

    expect($row)->not->toBeNull()
        ->and($row['city'])->toBe('Milano')
        ->and($row['province'])->toBe('Milano')
        ->and($row['region'])->toBe('Lombardia')
        ->and($row['country'])->toBe('Italia')
        ->and($row['postal_code'])->toBe('20100')
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

it('a company with no address has null derived geo/postal_code fields', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    Company::factory()->create(['denomination' => 'Lonely Srl']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/companies/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('denomination', 'Lonely Srl');

    expect($row['city'])->toBeNull()
        ->and($row['postal_code'])->toBeNull();
});

it('rows resolve the derived address fields with a bounded query count (no N+1)', function () {
    $actor = userWithCompanyAbilities(['viewAny']);

    foreach (range(1, 5) as $i) {
        $geo = companyTableGeoChain("City{$i}", "Province{$i}", "Region{$i}", "Country{$i}");
        $company = Company::factory()->create();
        Address::factory()->primary()->forCity($geo['city'])->for($company, 'addressable')->create();
    }

    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $this->postJson('/api/tables/companies/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->assertJsonCount(5, 'items');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // A fixed, small number of queries regardless of row count, never one per row.
    // Threshold includes +1 for CustomFieldAwareTableDefinition's cached
    // "does this domain have any active custom fields" lookup (spec 0021,
    // T6 — TableRegistry::resolve() now wraps every custom-fieldable domain;
    // the check is a single cache-miss query, never one per row).
    expect($queryCount)->toBeLessThan(12);
});

it('filters rows by the derived province set filter (whereHas by name)', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    $milano = companyTableGeoChain('Milano', 'Milano', 'Lombardia', 'Italia');
    $roma = companyTableGeoChain('Roma', 'Roma', 'Lazio', 'Italia');
    $companyA = Company::factory()->create(['denomination' => 'Team Milano']);
    Address::factory()->primary()->forCity($milano['city'])->for($companyA, 'addressable')->create();
    $companyB = Company::factory()->create(['denomination' => 'Team Roma']);
    Address::factory()->primary()->forCity($roma['city'])->for($companyB, 'addressable')->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['province' => ['filterType' => 'set', 'values' => ['Milano']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('denomination');
    expect($names->all())->toBe(['Team Milano']);
});

it('geo set filter: an English-stored name is shown and filtered by its Italian value', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    // Production reference data (world.sql) stores the province anglicized.
    $naples = companyTableGeoChain('Naples', 'Naples', 'Campania', 'Italy');
    $roma = companyTableGeoChain('Roma', 'Roma', 'Lazio', 'Italy');
    $companyA = Company::factory()->create(['denomination' => 'Team Napoli']);
    Address::factory()->primary()->forCity($naples['city'])->for($companyA, 'addressable')->create();
    $companyB = Company::factory()->create(['denomination' => 'Team Roma']);
    Address::factory()->primary()->forCity($roma['city'])->for($companyB, 'addressable')->create();
    Sanctum::actingAs($actor);

    // Row cell is localized to Italian...
    $rows = collect($this->postJson('/api/tables/companies/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->keyBy('denomination');
    expect($rows['Team Napoli']['province'])->toBe('Napoli');

    // ...and the set filter, sent the Italian value, matches the English DB row.
    $response = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['province' => ['filterType' => 'set', 'values' => ['Napoli']]],
    ])->assertOk();

    expect(collect($response->json('items'))->pluck('denomination')->all())->toBe(['Team Napoli']);
});

it('filters rows by the derived postal_code text filter', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    $companyA = Company::factory()->create(['denomination' => 'Has 20100']);
    Address::factory()->primary()->for($companyA, 'addressable')->create(['postal_code' => '20100']);
    $companyB = Company::factory()->create(['denomination' => 'Has 00100']);
    Address::factory()->primary()->for($companyB, 'addressable')->create(['postal_code' => '00100']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['postal_code' => ['filterType' => 'text', 'type' => 'contains', 'filter' => '201']],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('denomination');
    expect($names->all())->toBe(['Has 20100']);
});

it('sorts rows by the derived city name via a correlated subquery', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    $zed = companyTableGeoChain('Zeta City', 'Zeta Province', 'Zeta Region', 'Zeta Country');
    $amy = companyTableGeoChain('Amy City', 'Amy Province', 'Amy Region', 'Amy Country');
    $companyZ = Company::factory()->create(['denomination' => 'Z-company']);
    Address::factory()->primary()->forCity($zed['city'])->for($companyZ, 'addressable')->create();
    $companyA = Company::factory()->create(['denomination' => 'A-company']);
    Address::factory()->primary()->forCity($amy['city'])->for($companyA, 'addressable')->create();
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/companies/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'city', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.denomination');

    expect(array_search('A-company', $names, true))->toBeLessThan(array_search('Z-company', $names, true));
});

// ---------------------------------------------------------------------------
// AC-011 — /values distinct values for a geo set column
// ---------------------------------------------------------------------------

it('resolves distinct province names via /values', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    $milano = companyTableGeoChain('Milano', 'Milano', 'Lombardia', 'Italia');
    $company = Company::factory()->create();
    Address::factory()->primary()->forCity($milano['city'])->for($company, 'addressable')->create();
    Company::factory()->create(); // no address
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/companies/values', ['columnId' => 'province'])->assertOk();

    // `null` first: the blank entry ("(Vuoti)") — the row with no address.
    expect($response->json('data.values'))->toBe([null, 'Milano']);
});

it('/values search narrows the distinct province names', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    $milano = companyTableGeoChain('Milano', 'Milano', 'Lombardia', 'Italia');
    $roma = companyTableGeoChain('Roma', 'Roma', 'Lazio', 'Italia');
    $companyA = Company::factory()->create();
    Address::factory()->primary()->forCity($milano['city'])->for($companyA, 'addressable')->create();
    $companyB = Company::factory()->create();
    Address::factory()->primary()->forCity($roma['city'])->for($companyB, 'addressable')->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/companies/values', [
        'columnId' => 'province',
        'search' => 'mil',
    ])->assertOk();

    expect($response->json('data.values'))->toBe(['Milano']);
});

it('422 on the values endpoint when columnId is not filterable', function () {
    $actor = userWithCompanyAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/companies/values', ['columnId' => 'not-a-column'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});

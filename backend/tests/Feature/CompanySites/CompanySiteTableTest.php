<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Contact;
use App\Models\Country;
use App\Models\PersonalData;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithCompanySiteAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithCompanySiteAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("company-sites.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("company-sites.{$ability}");
        }

        return $user;
    }
}

/**
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function companySiteTableGeoChain(string $cityName, string $provinceName, string $stateName, string $countryName): array
{
    $country = Country::factory()->create(['name' => $countryName]);
    $state = State::factory()->create(['name' => $stateName, 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => $provinceName, 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => $cityName, 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

/**
 * Create a site whose personal-data card owns a primary address in $city. The
 * address lives on the card (morph `personable` → site), not the site morph.
 *
 * @param  array<string, mixed>  $siteAttrs
 * @param  array<string, mixed>  $addressAttrs
 */
function companySiteWithAddress(?City $city, array $siteAttrs = [], array $addressAttrs = []): CompanySite
{
    $site = CompanySite::factory()->create($siteAttrs);
    $card = PersonalData::factory()->company()->for($site, 'personable')->create();

    $address = Address::factory()->primary()->for($card, 'addressable');
    $address = $city !== null ? $address->forCity($city) : $address;
    $address->create($addressAttrs);

    return $site;
}

// ---------------------------------------------------------------------------
// AC-003 — columns config
// ---------------------------------------------------------------------------

it('returns the 11 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = userWithCompanySiteAbilities([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/company-sites/columns')->assertForbidden();

    $actor = userWithCompanySiteAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/company-sites/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('company-sites')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['defaultPagination']['limit'])->toBe(25)
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['logo_url', 'id', 'is_default', 'name', 'company', 'primary_contact', 'city', 'province', 'region', 'postal_code', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['logo_url']['sortable'])->toBeFalse()
        ->and($columns['logo_url']['filterable'])->toBeFalse()
        ->and($columns['is_default']['type'])->toBe('boolean')
        ->and($columns['is_default']['filterType'])->toBe('set')
        ->and($columns['is_default']['visible'])->toBeTrue()
        ->and($columns['name']['filterType'])->toBe('text')
        ->and($columns['company']['filterType'])->toBe('set')
        ->and($columns['company']['sortable'])->toBeTrue()
        ->and($columns['primary_contact']['type'])->toBe('tags')
        ->and($columns['primary_contact']['sortable'])->toBeFalse()
        ->and($columns['primary_contact']['filterable'])->toBeFalse()
        ->and($columns['city']['filterType'])->toBe('set')
        ->and($columns['city']['visible'])->toBeTrue()
        ->and($columns['province']['filterType'])->toBe('set')
        ->and($columns['province']['visible'])->toBeFalse()
        ->and($columns['region']['filterType'])->toBe('set')
        ->and($columns['postal_code']['filterType'])->toBe('text')
        ->and($columns['postal_code']['visible'])->toBeFalse()
        ->and($columns['postal_code']['hasFilterValues'])->toBeFalse()
        ->and($columns['created_at']['filterType'])->toBe('date');
});

it('hides action keys the user has no permission for', function () {
    $actor = userWithCompanySiteAbilities(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/company-sites/columns')->json('data');
    $actionKeys = collect($data['actions'])->pluck('key')->all();

    expect($actionKeys)->toContain('view')
        ->and($actionKeys)->not->toContain('edit')
        ->and($actionKeys)->not->toContain('delete');
});

// ---------------------------------------------------------------------------
// AC-004 — rows shape (derived contact/geo/postal_code), actions, no N+1
// ---------------------------------------------------------------------------

it('rows expose is_default, the derived primary_contact/geo/postal_code/company fields, logo_url and per-row actions', function () {
    $actor = userWithCompanySiteAbilities(['viewAny', 'view', 'update', 'delete']);
    $geo = companySiteTableGeoChain('Milano', 'Milano', 'Lombardia', 'Italia');
    $company = Company::factory()->create(['denomination' => 'Acme Holding']);
    $target = companySiteWithAddress($geo['city'], ['name' => 'Finance Site', 'is_default' => true, 'company_id' => $company->id], ['postal_code' => '20100']);
    Contact::factory()->email()->primary()->for($target->personalData, 'contactable')->create(['value' => 'finance@acme.test']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/company-sites/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    $row = collect($response->json('items'))->firstWhere('name', 'Finance Site');

    expect($row)->not->toBeNull()
        ->and($row['is_default'])->toBeTrue()
        ->and($row['city'])->toBe('Milano')
        ->and($row['province'])->toBe('Milano')
        ->and($row['region'])->toBe('Lombardia')
        ->and($row['postal_code'])->toBe('20100')
        ->and($row)->toHaveKey('logo_url')
        ->and($row['company'])->toBe(['id' => $company->id, 'name' => 'Acme Holding'])
        ->and(collect($row['primary_contact'])->pluck('value'))->toContain('finance@acme.test')
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

it('a site with no company has a null company row field', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    CompanySite::factory()->create(['name' => 'No Company Site']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/company-sites/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'No Company Site');

    expect($row['company'])->toBeNull();
});

it('filters rows by the derived company set filter (whereHas by denomination)', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    $acme = Company::factory()->create(['denomination' => 'Acme SpA']);
    $globex = Company::factory()->create(['denomination' => 'Globex SpA']);
    CompanySite::factory()->create(['name' => 'Acme Site', 'company_id' => $acme->id]);
    CompanySite::factory()->create(['name' => 'Globex Site', 'company_id' => $globex->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/company-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['company' => ['filterType' => 'set', 'values' => ['Acme SpA']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Acme Site']);
});

it('sorts rows by the derived company denomination via a correlated subquery', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    $zed = Company::factory()->create(['denomination' => 'Zeta Corp']);
    $amy = Company::factory()->create(['denomination' => 'Amy Corp']);
    CompanySite::factory()->create(['name' => 'Z-site', 'company_id' => $zed->id]);
    CompanySite::factory()->create(['name' => 'A-site', 'company_id' => $amy->id]);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/company-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'company', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.name');

    expect(array_search('A-site', $names, true))->toBeLessThan(array_search('Z-site', $names, true));
});

it('resolves distinct company denominations via /values', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    $acme = Company::factory()->create(['denomination' => 'Acme SpA']);
    CompanySite::factory()->create(['company_id' => $acme->id]);
    CompanySite::factory()->create(); // no company
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/company-sites/values', ['columnId' => 'company'])->assertOk();
    expect($response->json('data.values'))->toBe([null, 'Acme SpA']);
});

it('a site with no card has empty primary_contact and null derived geo/postal_code fields', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    CompanySite::factory()->create(['name' => 'Lonely Site']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/company-sites/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Lonely Site');

    expect($row['city'])->toBeNull()
        ->and($row['postal_code'])->toBeNull()
        ->and($row['primary_contact'])->toBe([]);
});

it('rows resolve the derived address fields with a bounded query count (no N+1)', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);

    foreach (range(1, 5) as $i) {
        $geo = companySiteTableGeoChain("City{$i}", "Province{$i}", "Region{$i}", "Country{$i}");
        companySiteWithAddress($geo['city']);
    }

    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    $this->postJson('/api/tables/company-sites/rows', ['startRow' => 0, 'endRow' => 25])
        ->assertOk()
        ->assertJsonCount(5, 'items');
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // A fixed, small number of queries regardless of row count, never one per
    // row. The card hop (site → personalData → contacts/addresses → geo) adds a
    // couple of constant eager-load queries vs the old direct-morph address.
    expect($queryCount)->toBeLessThan(14);
});

it('filters rows by the derived province set filter (whereHas by name)', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    $milano = companySiteTableGeoChain('Milano', 'Milano', 'Lombardia', 'Italia');
    $roma = companySiteTableGeoChain('Roma', 'Roma', 'Lazio', 'Italia');
    companySiteWithAddress($milano['city'], ['name' => 'Team Milano']);
    companySiteWithAddress($roma['city'], ['name' => 'Team Roma']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/company-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['province' => ['filterType' => 'set', 'values' => ['Milano']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Team Milano']);
});

it('filters rows by the derived postal_code text filter', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    companySiteWithAddress(null, ['name' => 'Has 20100'], ['postal_code' => '20100']);
    companySiteWithAddress(null, ['name' => 'Has 00100'], ['postal_code' => '00100']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/company-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['postal_code' => ['filterType' => 'text', 'type' => 'contains', 'filter' => '201']],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Has 20100']);
});

it('sorts rows by the derived city name via a correlated subquery', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    $zed = companySiteTableGeoChain('Zeta City', 'Zeta Province', 'Zeta Region', 'Zeta Country');
    $amy = companySiteTableGeoChain('Amy City', 'Amy Province', 'Amy Region', 'Amy Country');
    companySiteWithAddress($zed['city'], ['name' => 'Z-site']);
    companySiteWithAddress($amy['city'], ['name' => 'A-site']);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/company-sites/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'city', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.name');

    expect(array_search('A-site', $names, true))->toBeLessThan(array_search('Z-site', $names, true));
});

// ---------------------------------------------------------------------------
// AC-005 — /values distinct values, allow-list
// ---------------------------------------------------------------------------

it('resolves distinct province names and is_default values via /values', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    $milano = companySiteTableGeoChain('Milano', 'Milano', 'Lombardia', 'Italia');
    companySiteWithAddress($milano['city']);
    CompanySite::factory()->create(); // no card/address
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/company-sites/values', ['columnId' => 'province'])->assertOk();
    // `null` first: the blank entry ("(Vuoti)") — the row with no address.
    expect($response->json('data.values'))->toBe([null, 'Milano']);
});

it('/values search narrows the distinct province names', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    $milano = companySiteTableGeoChain('Milano', 'Milano', 'Lombardia', 'Italia');
    $roma = companySiteTableGeoChain('Roma', 'Roma', 'Lazio', 'Italia');
    companySiteWithAddress($milano['city']);
    companySiteWithAddress($roma['city']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/company-sites/values', ['columnId' => 'province', 'search' => 'mil'])->assertOk();

    expect($response->json('data.values'))->toBe(['Milano']);
});

it('422 on the values endpoint when columnId is not allow-listed', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/company-sites/values', ['columnId' => 'not-a-column'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});

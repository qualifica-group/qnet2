<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\CompanySiteBank;
use App\Models\Country;
use App\Models\PersonalData;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

if (! function_exists('companySiteGeoChain')) {
    /**
     * @return array{country: Country, state: State, province: Province, city: City}
     */
    function companySiteGeoChain(): array
    {
        $country = Country::factory()->create(['name' => 'Italia']);
        $state = State::factory()->create(['name' => 'Lombardia', 'country_id' => $country->id]);
        $province = Province::factory()->create(['name' => 'Milano', 'state_id' => $state->id, 'country_id' => $country->id]);
        $city = City::factory()->create(['name' => 'Milano', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

        return compact('country', 'state', 'province', 'city');
    }
}

if (! function_exists('companySiteCompanyProfile')) {
    /**
     * A minimal valid nested personal_data payload (company, no children).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function companySiteCompanyProfile(array $overrides = []): array
    {
        return array_merge([
            'type' => 'company',
            'company_name' => 'Acme SpA',
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/company-sites/{companySite} (AC-006 shape reference)
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape, personal_data (card+address) + banks + permissions', function () {
    $actor = userWithCompanySiteAbilities(['view']);
    $geo = companySiteGeoChain();
    $target = CompanySite::factory()->create(['name' => 'Sede Nord']);
    $card = PersonalData::factory()->company()->for($target, 'personable')->create(['company_name' => 'Nord SpA']);
    Address::factory()->primary()->forCity($geo['city'])->for($card, 'addressable')->create(['line1' => 'Via Roma 1']);
    CompanySiteBank::factory()->for($target)->create(['name' => 'Banca Uno']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/company-sites/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Sede Nord')
        ->assertJsonPath('data.is_default', false)
        ->assertJsonPath('data.personal_data.company_name', 'Nord SpA')
        ->assertJsonPath('data.personal_data.addresses.0.line1', 'Via Roma 1')
        ->assertJsonPath('data.personal_data.addresses.0.city_id', $geo['city']->id)
        ->assertJsonPath('data.banks.0.name', 'Banca Uno');

    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: exposes company_id and the nested company reference when set', function () {
    $actor = userWithCompanySiteAbilities(['view']);
    $company = Company::factory()->create(['denomination' => 'Acme Holding SpA']);
    $target = CompanySite::factory()->create(['company_id' => $company->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/company-sites/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.company_id', $company->id)
        ->assertJsonPath('data.company.id', $company->id)
        ->assertJsonPath('data.company.label', 'Acme Holding SpA');
});

it('show: company is absent from the payload when the site has no company', function () {
    $actor = userWithCompanySiteAbilities(['view']);
    $target = CompanySite::factory()->create(['company_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/company-sites/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.company_id', null);

    expect($response->json('data'))->not->toHaveKey('company');
});

it('show: 403 without company-sites.view', function () {
    $actor = userWithCompanySiteAbilities([]);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/company-sites/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent site', function () {
    $actor = userWithCompanySiteAbilities(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/company-sites/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/company-sites (AC-007)
// ---------------------------------------------------------------------------

it('create: 201 + persists the site with card, address and banks (preferred bank flagged)', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    $city = City::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', [
        'name' => 'Sede Sud',
        'personal_data' => companySiteCompanyProfile([
            'vat_number' => 'IT12345678903',
            'addresses' => [['line1' => 'Via Napoli 5', 'city_id' => $city->id]],
            'contacts' => [['type' => 'email', 'value' => 'sud@acme.test', 'is_primary' => true]],
        ]),
        'banks' => [['name' => 'Banca Uno', 'iban' => 'IT60X0542811101000000123456', 'is_primary' => true]],
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Sede Sud')
        ->assertJsonPath('data.personal_data.company_name', 'Acme SpA')
        // Stored canonical (user directive 2026-07-23): the optional IT prefix is dropped.
        ->assertJsonPath('data.personal_data.vat_number', '12345678903')
        ->assertJsonPath('data.personal_data.addresses.0.line1', 'Via Napoli 5')
        ->assertJsonPath('data.personal_data.contacts.0.value', 'sud@acme.test')
        ->assertJsonPath('data.banks.0.name', 'Banca Uno')
        ->assertJsonPath('data.banks.0.is_primary', true);

    $site = CompanySite::first();
    $this->assertDatabaseHas('company_sites', ['name' => 'Sede Sud']);
    $this->assertDatabaseHas('company_site_banks', ['name' => 'Banca Uno', 'is_primary' => true]);
    expect($site->personalData)->not->toBeNull()
        ->and($site->personalData->personable_type)->toBe('company_site');
});

it('create: 201 without personal_data/banks (card is null)', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', ['name' => 'No Frills'])
        ->assertCreated()
        ->assertJsonPath('data.personal_data', null)
        ->assertJsonPath('data.banks', []);
});

it('create: 201 + persists company_id when it references an existing company', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    $company = Company::factory()->create(['denomination' => 'Acme Holding SpA']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', ['name' => 'Sede Est', 'company_id' => $company->id])
        ->assertCreated()
        ->assertJsonPath('data.company_id', $company->id)
        ->assertJsonPath('data.company.label', 'Acme Holding SpA');

    $this->assertDatabaseHas('company_sites', ['name' => 'Sede Est', 'company_id' => $company->id]);
});

it('create: 422 when company_id does not reference an existing company', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', ['name' => 'X', 'company_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('company_id');
});

it('create: 422 when name is missing', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', [])
        ->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('create: 422 when a nested address city id does not exist', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', [
        'name' => 'X',
        'personal_data' => companySiteCompanyProfile([
            'addresses' => [['line1' => 'Via X', 'city_id' => 999999]],
        ]),
    ])->assertStatus(422)->assertJsonValidationErrors(['personal_data.addresses.0.city_id']);
});

it('create: 422 when a nested address is missing city_id (product decision: geo-located on create)', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', [
        'name' => 'X',
        'personal_data' => companySiteCompanyProfile([
            'addresses' => [['line1' => 'Via X']],
        ]),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.addresses.0.city_id');

    $this->assertDatabaseMissing('company_sites', ['name' => 'X']);
});

it('create: 422 when personal_data.addresses carries more than one address (max 1)', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', [
        'name' => 'X',
        'personal_data' => companySiteCompanyProfile([
            'addresses' => [['line1' => 'Via A'], ['line1' => 'Via B']],
        ]),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.addresses');
});

it('create: only one bank stays primary when several are flagged (single-primary invariant)', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', [
        'name' => 'X',
        'banks' => [
            ['name' => 'Banca Uno', 'is_primary' => true],
            ['name' => 'Banca Due', 'is_primary' => true],
        ],
    ])->assertCreated();

    expect(CompanySiteBank::where('is_primary', true)->count())->toBe(1);
});

it('create: 201 with a non-SEPA-shaped IBAN (product decision: free text, only length-capped)', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', [
        'name' => 'X',
        'banks' => [['name' => 'Free Text Bank', 'iban' => 'not-an-iban']],
    ])->assertCreated()->assertJsonPath('data.banks.0.name', 'Free Text Bank');

    $this->assertDatabaseHas('company_site_banks', ['iban' => 'not-an-iban']);
});

it('create: 403 without company-sites.create', function () {
    $actor = userWithCompanySiteAbilities([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/company-sites/{companySite} (AC-012)
// ---------------------------------------------------------------------------

it('delete: 204, removes the site and cascades card, address + banks', function () {
    $actor = userWithCompanySiteAbilities(['delete']);
    $target = CompanySite::factory()->create();
    $card = PersonalData::factory()->company()->for($target, 'personable')->create();
    $address = Address::factory()->primary()->for($card, 'addressable')->create();
    $bank = CompanySiteBank::factory()->for($target)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/company-sites/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('company_sites', ['id' => $target->id]);
    $this->assertDatabaseMissing('personal_data', ['id' => $card->id]);
    $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    $this->assertDatabaseMissing('company_site_banks', ['id' => $bank->id]);
});

it('delete: 403 without company-sites.delete', function () {
    $actor = userWithCompanySiteAbilities([]);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/company-sites/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent site', function () {
    $actor = userWithCompanySiteAbilities(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/company-sites/999999')->assertNotFound();
});

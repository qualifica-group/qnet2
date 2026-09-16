<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Country;
use App\Models\OperationalSite;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
 * A real geo chain (country -> state/region -> province -> city) so tests can
 * assert both the ids AND the resolved names.
 *
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function siteGeoChain(): array
{
    $country = Country::factory()->create(['name' => 'Italia']);
    $state = State::factory()->create(['name' => 'Lombardia', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milano', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milano', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

// ---------------------------------------------------------------------------
// show — GET /api/operational-sites/{operationalSite} (AC-010)
// ---------------------------------------------------------------------------

it('show: 200 with the flat address shape, ids AND names', function () {
    $actor = userWithSiteAbilities(['view']);
    $geo = siteGeoChain();
    $target = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($geo['city'])->for($target, 'addressable')->create([
        'line1' => 'Via Roma 1',
        'postal_code' => '20100',
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/operational-sites/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.line1', 'Via Roma 1')
        ->assertJsonPath('data.postal_code', '20100')
        ->assertJsonPath('data.city_id', $geo['city']->id)
        ->assertJsonPath('data.city.name', 'Milano')
        ->assertJsonPath('data.province.name', 'Milano')
        ->assertJsonPath('data.region.name', 'Lombardia')
        ->assertJsonPath('data.country.name', 'Italia')
        ->assertJsonPath('data.is_active', true);

    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: address fields are null when the site has no address', function () {
    $actor = userWithSiteAbilities(['view']);
    $target = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/operational-sites/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.line1', null)
        ->assertJsonPath('data.city', null);
});

it('show: 403 without operational-sites.view', function () {
    $actor = userWithSiteAbilities([]);
    $target = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/operational-sites/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent site', function () {
    $actor = userWithSiteAbilities(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/operational-sites/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/operational-sites (AC-009)
// ---------------------------------------------------------------------------

it('create: 201 + persists the site with a single primary address', function () {
    $actor = userWithSiteAbilities(['create']);
    $geo = siteGeoChain();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/operational-sites', [
        'line1' => 'Via Milano 5',
        'postal_code' => '10100',
        'country_id' => $geo['country']->id,
        'state_id' => $geo['state']->id,
        'province_id' => $geo['province']->id,
        'city_id' => $geo['city']->id,
    ])->assertCreated()
        ->assertJsonPath('data.line1', 'Via Milano 5')
        ->assertJsonPath('data.city.name', 'Milano');

    $this->assertDatabaseHas('operational_sites', ['id' => $response->json('data.id')]);
    $this->assertDatabaseHas('addresses', [
        'addressable_type' => 'operational_site',
        'addressable_id' => $response->json('data.id'),
        'line1' => 'Via Milano 5',
        'is_primary' => true,
    ]);
});

it('create: postal_code is nullable', function () {
    $actor = userWithSiteAbilities(['create']);
    $geo = siteGeoChain();
    Sanctum::actingAs($actor);

    $this->postJson('/api/operational-sites', ['line1' => 'Via X', 'city_id' => $geo['city']->id])
        ->assertCreated()
        ->assertJsonPath('data.postal_code', null);
});

it('create: 422 when line1 is missing', function () {
    $actor = userWithSiteAbilities(['create']);
    $geo = siteGeoChain();
    Sanctum::actingAs($actor);

    $this->postJson('/api/operational-sites', ['city_id' => $geo['city']->id])
        ->assertStatus(422)->assertJsonValidationErrors('line1');
});

it('create: 422 when city_id is missing', function () {
    $actor = userWithSiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/operational-sites', ['line1' => 'Via X'])
        ->assertStatus(422)->assertJsonValidationErrors('city_id');
});

it('create: 422 when a geo id does not exist', function () {
    $actor = userWithSiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/operational-sites', ['line1' => 'Via X', 'city_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('city_id');
});

it('create: 403 without operational-sites.create', function () {
    $actor = userWithSiteAbilities([]);
    $geo = siteGeoChain();
    Sanctum::actingAs($actor);

    $this->postJson('/api/operational-sites', ['line1' => 'Via X', 'city_id' => $geo['city']->id])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/operational-sites/{operationalSite} (AC-011)
// ---------------------------------------------------------------------------

it('update: PATCH partial (only line1) leaves postal_code/geo untouched, no duplicate address', function () {
    $actor = userWithSiteAbilities(['update']);
    $geo = siteGeoChain();
    $target = OperationalSite::factory()->create();
    $address = Address::factory()->primary()->forCity($geo['city'])->for($target, 'addressable')->create([
        'line1' => 'Original Street',
        'postal_code' => '00100',
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/operational-sites/{$target->id}", ['line1' => 'New Street'])
        ->assertOk()
        ->assertJsonPath('data.line1', 'New Street')
        ->assertJsonPath('data.postal_code', '00100')
        ->assertJsonPath('data.city.name', 'Milano');

    expect($target->addresses()->count())->toBe(1)
        ->and($address->fresh()->line1)->toBe('New Street')
        ->and($address->fresh()->is_primary)->toBeTrue();
});

it('update: PATCH {postal_code: null} clears the CAP only', function () {
    $actor = userWithSiteAbilities(['update']);
    $target = OperationalSite::factory()->create();
    Address::factory()->primary()->for($target, 'addressable')->create(['postal_code' => '00100', 'line1' => 'Kept Street']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/operational-sites/{$target->id}", ['postal_code' => null])
        ->assertOk()
        ->assertJsonPath('data.postal_code', null)
        ->assertJsonPath('data.line1', 'Kept Street');
});

it('update: PATCH creates the address when the site had none', function () {
    $actor = userWithSiteAbilities(['update']);
    $geo = siteGeoChain();
    $target = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/operational-sites/{$target->id}", [
        'line1' => 'Brand New Street',
        'city_id' => $geo['city']->id,
    ])->assertOk()->assertJsonPath('data.line1', 'Brand New Street');

    expect($target->addresses()->count())->toBe(1)
        ->and($target->addresses()->first()->is_primary)->toBeTrue();
});

it('create: is_active defaults to true and can be submitted as false', function () {
    $actor = userWithSiteAbilities(['create']);
    $geo = siteGeoChain();
    Sanctum::actingAs($actor);

    $this->postJson('/api/operational-sites', ['line1' => 'Via Attiva 1', 'city_id' => $geo['city']->id])
        ->assertCreated()
        ->assertJsonPath('data.is_active', true);

    $inactiveId = $this->postJson('/api/operational-sites', [
        'line1' => 'Via Spenta 2',
        'city_id' => $geo['city']->id,
        'is_active' => false,
    ])->assertCreated()
        ->assertJsonPath('data.is_active', false)
        ->json('data.id');

    $this->assertDatabaseHas('operational_sites', ['id' => $inactiveId, 'is_active' => false]);
});

it('create: 422 when is_active is not a boolean', function () {
    $actor = userWithSiteAbilities(['create']);
    $geo = siteGeoChain();
    Sanctum::actingAs($actor);

    $this->postJson('/api/operational-sites', ['line1' => 'Via X', 'city_id' => $geo['city']->id, 'is_active' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['is_active']);
});

it('update: PATCH {is_active: false} deactivates the site and leaves the address untouched', function () {
    $actor = userWithSiteAbilities(['update']);
    $target = OperationalSite::factory()->create();
    Address::factory()->primary()->for($target, 'addressable')->create(['line1' => 'Kept Street']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/operational-sites/{$target->id}", ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.line1', 'Kept Street');

    expect($target->fresh()->is_active)->toBeFalse();

    $this->patchJson("/api/operational-sites/{$target->id}", ['line1' => 'Other Street'])
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
});

it('update: 403 without operational-sites.update', function () {
    $actor = userWithSiteAbilities([]);
    $target = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/operational-sites/{$target->id}", ['line1' => 'Nope'])->assertForbidden();
});

it('update: 404 for a non-existent site', function () {
    $actor = userWithSiteAbilities(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/operational-sites/999999', ['line1' => 'Ghost'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/operational-sites/{operationalSite} (AC-013)
// ---------------------------------------------------------------------------

it('delete: 204, removes the site and cascades its address', function () {
    $actor = userWithSiteAbilities(['delete']);
    $target = OperationalSite::factory()->create();
    $address = Address::factory()->primary()->for($target, 'addressable')->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/operational-sites/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('operational_sites', ['id' => $target->id]);
    $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
});

it('delete: 403 without operational-sites.delete', function () {
    $actor = userWithSiteAbilities([]);
    $target = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/operational-sites/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent site', function () {
    $actor = userWithSiteAbilities(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/operational-sites/999999')->assertNotFound();
});

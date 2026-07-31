<?php

use App\Models\Address;
use App\Models\BusinessFunction;
use App\Models\City;
use App\Models\OperationalSite;
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

// ---------------------------------------------------------------------------
// AC-010 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/operational-sites/for-select')->assertUnauthorized();
});

it('allows actors without operational-sites.viewAny (200 — ADR 0011 amended)', function () {
    $actor = userWithSiteAbilities([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/operational-sites/for-select')->assertOk();
});

it('allows actors with operational-sites.viewAny (200) and returns the paginated envelope', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    OperationalSite::factory()->withAddress()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/operational-sites/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ])
        ->assertJsonPath('export_link', null);
});

// ---------------------------------------------------------------------------
// AC-010 — item shape (label composed from the address: "line1 - city")
// ---------------------------------------------------------------------------

it('maps a site to { id, label: "line1 - city", subtitle: postal_code, meta: Regione }', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $city = City::factory()->create(['name' => 'Springfield']);
    $target = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($city)->for($target, 'addressable')->create([
        'line1' => 'Evergreen Terrace 742',
        'postal_code' => '00100',
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/operational-sites/for-select?search=Evergreen')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $target->id);
    $state = $city->state;

    // meta carries the site's Regione (directive 2026-07-21) for the Lead
    // form's auto-fill; the City factory always seeds a state.
    expect($item)->toMatchArray([
        'id' => $target->id,
        'label' => 'Evergreen Terrace 742 - Springfield',
        'subtitle' => '00100',
        'meta' => ['state_id' => $state->id, 'state_label' => $state->localizedName()],
    ])->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'subtitle', 'meta']);
});

it('omits meta when the primary address has no region', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $target = OperationalSite::factory()->create();
    Address::factory()->primary()->for($target, 'addressable')->create([
        'line1' => 'No Region Street 9',
        'postal_code' => null,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/operational-sites/for-select?search=No+Region')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect(array_keys($item))->not->toContain('meta');
});

it('falls back to line1 alone when the address has no city', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $target = OperationalSite::factory()->create();
    Address::factory()->primary()->for($target, 'addressable')->create([
        'line1' => 'Unmapped Street 1',
        'postal_code' => null,
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/operational-sites/for-select?search=Unmapped')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Unmapped Street 1'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

// ---------------------------------------------------------------------------
// AC-010 — search (line1 and city name)
// ---------------------------------------------------------------------------

it('searches by address line1', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $match = OperationalSite::factory()->withAddress()->create();
    $match->addresses()->first()->update(['line1' => 'Alphonse Target Street']);
    OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/operational-sites/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

it('searches by city name', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $city = City::factory()->create(['name' => 'Uniqueville']);
    $match = OperationalSite::factory()->withAddress($city)->create();
    OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/operational-sites/for-select?search=Uniqueville')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-010 — ids[] hydration
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $city = City::factory()->create(['name' => 'Zephyrland']);
    $searchMatch = OperationalSite::factory()->withAddress($city)->create();
    $selected = OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/operational-sites/for-select?search=Zephyrland&ids[]={$selected->id}")
        ->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

// ---------------------------------------------------------------------------
// AC-010 — validation bounds
// ---------------------------------------------------------------------------

it('rejects a limit above 100 (422)', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/operational-sites/for-select?limit=101')
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');
});

// ---------------------------------------------------------------------------
// AC-052 — business_function_id scope (spec 0040 BR-4)
// ---------------------------------------------------------------------------

it('business_function_id restricts the list to sites linked to that function', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    $function = BusinessFunction::factory()->create();
    $linked = OperationalSite::factory()->withAddress()->create();
    $function->operationalSites()->attach($linked);
    OperationalSite::factory()->withAddress()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/operational-sites/for-select?business_function_id={$function->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($linked->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('422 when business_function_id does not reference an existing function', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/operational-sites/for-select?business_function_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('business_function_id');
});

it('without business_function_id behaves exactly as before (every site)', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    OperationalSite::factory()->withAddress()->count(2)->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/operational-sites/for-select')->assertOk();

    expect($response->json('pagination.total'))->toBe(2);
});

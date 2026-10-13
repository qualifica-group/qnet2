<?php

use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// spec 0225 — AC-001/AC-002: GET /api/provinces/for-select. International mode
// is made explicit (the real .env reaches the test process, see
// StateForSelectTest); national mode has its own block below.
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('geo.default_country_iso2', null);
});

it('requires authentication (401)', function () {
    $this->getJson('/api/provinces/for-select')->assertUnauthorized();
});

it('maps a province to { id, label: name, subtitle: region name }', function () {
    Sanctum::actingAs(User::factory()->create());
    $state = State::factory()->create(['name' => 'Veneto']);
    $province = Province::factory()->forState($state)->create(['name' => 'Treviso']);

    $response = $this->getJson('/api/provinces/for-select?search=Treviso')
        ->assertOk()
        ->assertJsonStructure(['items', 'export_link', 'pagination' => ['total', 'offset', 'limit', 'total_pages']]);

    expect($response->json('items'))->toBe([['id' => $province->id, 'label' => 'Treviso', 'subtitle' => 'Veneto']]);
});

it('searches by name', function () {
    Sanctum::actingAs(User::factory()->create());
    $match = Province::factory()->create(['name' => 'Alphonse Target']);
    Province::factory()->create(['name' => 'Someone Else']);

    $response = $this->getJson('/api/provinces/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

it('appends ids[] filtered out by search without inflating total', function () {
    Sanctum::actingAs(User::factory()->create());
    $searchMatch = Province::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = Province::factory()->create(['name' => 'Quentin Selected']);

    $response = $this->getJson("/api/provinces/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/provinces/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

it('eager-loads the region instead of one query per province', function () {
    Sanctum::actingAs(User::factory()->create());
    Province::factory()->count(5)->create();

    DB::enableQueryLog();
    $this->getJson('/api/provinces/for-select')->assertOk();
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    $stateQueries = $queries->filter(fn (string $sql) => str_contains($sql, 'from `states`') || str_contains($sql, 'from "states"'));

    expect($stateQueries)->toHaveCount(1);
});

it('national mode: lists only the provinces of the configured country, ids[] still hydrates a foreign one', function () {
    Sanctum::actingAs(User::factory()->create());
    $italy = Country::factory()->create(['iso2' => 'IT']);
    $treviso = Province::factory()->create(['name' => 'Treviso', 'country_id' => $italy->id]);
    $foreign = Province::factory()->create(['name' => 'Oberbayern']);
    config()->set('geo.default_country_iso2', 'IT');

    $listed = $this->getJson('/api/provinces/for-select')->assertOk();
    expect(collect($listed->json('items'))->pluck('id')->all())->toBe([$treviso->id]);

    $hydrated = $this->getJson("/api/provinces/for-select?ids[]={$foreign->id}")->assertOk();
    expect(collect($hydrated->json('items'))->pluck('id'))->toContain($foreign->id)
        ->and($hydrated->json('pagination.total'))->toBe(1);
});

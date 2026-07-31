<?php

use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('sectorForSelectUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function sectorForSelectUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("sectors.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("sectors.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-005 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/sectors/for-select')->assertUnauthorized();
});

it('allows actors without sectors.viewAny (200 — ADR 0011 amended)', function () {
    $actor = sectorForSelectUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/sectors/for-select')->assertOk();
});

it('allows actors with sectors.viewAny (200) and returns the paginated envelope', function () {
    $actor = sectorForSelectUserWith(['viewAny']);
    Sector::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/sectors/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-005 — item shape + search + route order
// ---------------------------------------------------------------------------

it('maps a sector to { id, label: name }', function () {
    $actor = sectorForSelectUserWith(['viewAny']);
    $target = Sector::factory()->create(['name' => 'Renewable Energy']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/sectors/for-select?search=Renewable Energy')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Renewable Energy'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

it('searches by name', function () {
    $actor = sectorForSelectUserWith(['viewAny']);
    $match = Sector::factory()->create(['name' => 'Alphonse Target']);
    Sector::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/sectors/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

it('resolves the literal for-select segment BEFORE the {sector} wildcard', function () {
    $actor = sectorForSelectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/sectors/for-select')->assertOk();
});

it('resolves the literal tree segment independently of for-select (both above the wildcard)', function () {
    $actor = sectorForSelectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/sectors/tree')->assertOk();
    $this->getJson('/api/sectors/for-select')->assertOk();
});

<?php

use App\Models\ReferentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('referentTypeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function referentTypeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("referent-types.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("referent-types.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-006 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/referent-types/for-select')->assertUnauthorized();
});

it('allows actors without referent-types.viewAny (200 — ADR 0011 amended)', function () {
    $actor = referentTypeUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/referent-types/for-select')->assertOk();
});

it('allows actors with referent-types.viewAny (200) and returns the paginated envelope', function () {
    $actor = referentTypeUserWith(['viewAny']);
    ReferentType::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/referent-types/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-006 — item shape + search
// ---------------------------------------------------------------------------

it('maps a referent type to { id, label: name }', function () {
    $actor = referentTypeUserWith(['viewAny']);
    $target = ReferentType::factory()->create(['name' => 'Legal Counsel']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/referent-types/for-select?search=Legal Counsel')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Legal Counsel'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

it('searches by name', function () {
    $actor = referentTypeUserWith(['viewAny']);
    $match = ReferentType::factory()->create(['name' => 'Alphonse Target']);
    ReferentType::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/referent-types/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-006 — ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = referentTypeUserWith(['viewAny']);
    $searchMatch = ReferentType::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = ReferentType::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/referent-types/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = referentTypeUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/referent-types/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

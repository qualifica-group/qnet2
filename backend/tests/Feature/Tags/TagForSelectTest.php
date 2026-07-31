<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('tagUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function tagUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("tags.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("tags.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-006 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/tags/for-select')->assertUnauthorized();
});

it('allows actors without tags.viewAny (200 — ADR 0011 amended)', function () {
    $actor = tagUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tags/for-select')->assertOk();
});

it('allows actors with tags.viewAny (200) and returns the paginated envelope', function () {
    $actor = tagUserWith(['viewAny']);
    Tag::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/tags/for-select')
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

it('maps a tag to { id, label: name }', function () {
    $actor = tagUserWith(['viewAny']);
    $target = Tag::factory()->create(['name' => 'Trade Show']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/tags/for-select?search=Trade Show')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Trade Show'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

it('searches by name', function () {
    $actor = tagUserWith(['viewAny']);
    $match = Tag::factory()->create(['name' => 'Alphonse Target']);
    Tag::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/tags/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-006 — ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = tagUserWith(['viewAny']);
    $searchMatch = Tag::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = Tag::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/tags/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = tagUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tags/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

<?php

use App\Models\Contact;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('referentForSelectUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function referentForSelectUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("referents.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("referents.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-004 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/referents/for-select')->assertUnauthorized();
});

it('allows actors without referents.viewAny (200 — ADR 0011 amended)', function () {
    $actor = referentForSelectUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/referents/for-select')->assertOk();
});

it('allows actors with referents.viewAny (200) and returns the paginated envelope', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    Referent::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/referents/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-004 — item shape + search + route order
// ---------------------------------------------------------------------------

it('maps a referent to { id, label: name }', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    $target = Referent::factory()->create(['name' => 'Ada Lovelace']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/referents/for-select?search=Ada Lovelace')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    // `meta` is always present now (spec 0040 A-4), like registries/for-select.
    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'Ada Lovelace'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'meta'])
        ->and($item['meta'])->toHaveKey('contacts');
});

it('searches by name', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    $match = Referent::factory()->create(['name' => 'Alphonse Target']);
    Referent::factory()->create(['name' => 'Someone Else']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/referents/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    $searchMatch = Referent::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = Referent::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/referents/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('resolves the literal for-select segment BEFORE the {referent} wildcard', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    // A non-numeric "id" would 404/fail model binding if the wildcard route
    // matched first; the literal for-select controller must win.
    $this->getJson('/api/referents/for-select')->assertOk();
});

// ---------------------------------------------------------------------------
// AC-051 — registry_id scope (spec 0040 BR-4)
// ---------------------------------------------------------------------------

it('registry_id restricts the list to referents linked to that registry', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    $registry = Registry::factory()->create();
    $linked = Referent::factory()->create();
    $registry->referents()->attach($linked);
    Referent::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/referents/for-select?registry_id={$registry->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($linked->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('422 when registry_id does not reference an existing registry', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/referents/for-select?registry_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('registry_id');
});

it('without registry_id behaves exactly as before (every referent)', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    Referent::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/referents/for-select')->assertOk();

    expect($response->json('pagination.total'))->toBe(2);
});

// ---------------------------------------------------------------------------
// AC-091 — meta.contacts (spec 0040 A-4): ONLY primary contacts
// ---------------------------------------------------------------------------

it('meta.contacts carries ONLY the referent primary contacts', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    $referent = Referent::factory()->withPersonalData()->create(['name' => 'Grace Hopper']);
    Contact::factory()->email()->primary()->for($referent->personalData, 'contactable')->create([
        'value' => 'grace@example.test',
    ]);
    Contact::factory()->phone()->for($referent->personalData, 'contactable')->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/referents/for-select?ids[]={$referent->id}")->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $referent->id);

    expect($item['meta']['contacts'])->toHaveCount(1)
        ->and($item['meta']['contacts'][0])->toMatchArray([
            'type' => 'email',
            'value' => 'grace@example.test',
            'is_primary' => true,
        ]);
});

it('meta.contacts is [] for a referent without a card or without primary contacts', function () {
    $actor = referentForSelectUserWith(['viewAny']);
    $bare = Referent::factory()->create();
    $noPrimary = Referent::factory()->withPersonalData()->create();
    Contact::factory()->phone()->for($noPrimary->personalData, 'contactable')->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/referents/for-select?ids[]={$bare->id}&ids[]={$noPrimary->id}")->assertOk();
    $items = collect($response->json('items'))->keyBy('id');

    expect($items[$bare->id]['meta']['contacts'])->toBe([])
        ->and($items[$noPrimary->id]['meta']['contacts'])->toBe([]);
});

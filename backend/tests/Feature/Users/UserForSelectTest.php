<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithViewAnyUsers')) {
    /**
     * A non super-admin actor granted exactly the given `users.*` abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function userWithViewAnyUsers(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("users.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// Auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/users/for-select')->assertUnauthorized();
});

it('allows actors without users.viewAny (200 — ADR 0011 amended)', function () {
    $actor = userWithViewAnyUsers([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/users/for-select')->assertOk();
});

it('allows actors with users.viewAny (200) and returns the paginated envelope', function () {
    $actor = userWithViewAnyUsers(['viewAny']);
    User::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/users/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label', 'subtitle']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ])
        ->assertJsonPath('export_link', null);
});

// ---------------------------------------------------------------------------
// Item shape
// ---------------------------------------------------------------------------

it('maps a user to { id, label: name, subtitle: email }', function () {
    $actor = userWithViewAnyUsers(['viewAny']);
    $target = User::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@acme.test']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/users/for-select?search=Jane Doe')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray([
        'id' => $target->id,
        'label' => 'Jane Doe',
        'subtitle' => 'jane@acme.test',
    ])->and(array_keys($item))->toEqualCanonicalizing(['id', 'label', 'subtitle']);
});

it('includes avatar_url when the selected user has an avatar', function () {
    Storage::fake('local');

    $actor = userWithViewAnyUsers(['viewAny']);
    $target = User::factory()->create(['name' => 'Avatar User', 'email' => 'avatar@acme.test']);
    $target->attach(UploadedFile::fake()->image('avatar.png'), User::AVATAR_COLLECTION);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/users/for-select?search=Avatar User')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item['avatar_url'])->toStartWith('data:image/')
        ->and($item['avatar_url'])->toContain(';base64,')
        ->and($item['label'])->toBe('Avatar User')
        ->and($item['subtitle'])->toBe('avatar@acme.test');
});

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

it('respects offset/limit and reports total + total_pages', function () {
    $actor = userWithViewAnyUsers(['viewAny']); // actor counts as a user too
    User::factory()->count(29)->create();        // 29 + actor = 30 users
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/users/for-select?offset=0&limit=10')
        ->assertOk()
        ->assertJsonPath('pagination.total', 30)
        ->assertJsonPath('pagination.offset', 0)
        ->assertJsonPath('pagination.limit', 10)
        ->assertJsonPath('pagination.total_pages', 3);

    expect($response->json('items'))->toHaveCount(10);

    $page2 = $this->getJson('/api/users/for-select?offset=10&limit=10')->assertOk();
    expect($page2->json('items'))->toHaveCount(10);

    // Pages do not overlap.
    $firstIds = collect($response->json('items'))->pluck('id');
    $secondIds = collect($page2->json('items'))->pluck('id');
    expect($firstIds->intersect($secondIds))->toBeEmpty();
});

it('defaults to limit 25 when none is given', function () {
    $actor = userWithViewAnyUsers(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/users/for-select')
        ->assertOk()
        ->assertJsonPath('pagination.limit', 25);
});

// ---------------------------------------------------------------------------
// Search
// ---------------------------------------------------------------------------

it('searches by name', function () {
    $actor = userWithViewAnyUsers(['viewAny']);
    $match = User::factory()->create(['name' => 'Alphonse Target', 'email' => 'a@x.test']);
    User::factory()->create(['name' => 'Someone Else', 'email' => 'b@x.test']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/users/for-select?search=Alphonse')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

it('searches by email', function () {
    $actor = userWithViewAnyUsers(['viewAny']);
    $match = User::factory()->create(['name' => 'No Match Name', 'email' => 'needle@acme.test']);
    User::factory()->create(['name' => 'Other', 'email' => 'haystack@acme.test']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/users/for-select?search=needle')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// ids[] hydration
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = userWithViewAnyUsers(['viewAny']);
    $searchMatch = User::factory()->create(['name' => 'Zephyr Searchable', 'email' => 'z@x.test']);
    $selected = User::factory()->create(['name' => 'Quentin Selected', 'email' => 'q@x.test']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/for-select?search=Zephyr&ids[]={$selected->id}")
        ->assertOk();

    $ids = collect($response->json('items'))->pluck('id');

    // Both the searched match AND the explicitly selected (out-of-search) user.
    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        // total reflects ONLY the searchable population (the search match), not the
        // hydrated id.
        ->and($response->json('pagination.total'))->toBe(1);
});

it('does not duplicate an id already on the page', function () {
    $actor = userWithViewAnyUsers(['viewAny']);
    $onPage = User::factory()->create(['name' => 'Dedup Target', 'email' => 'd@x.test']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/for-select?search=Dedup&ids[]={$onPage->id}")
        ->assertOk();

    $occurrences = collect($response->json('items'))->where('id', $onPage->id)->count();
    expect($occurrences)->toBe(1);
});

// ---------------------------------------------------------------------------
// Validation bounds
// ---------------------------------------------------------------------------

it('rejects a limit above 100 (422)', function () {
    $actor = userWithViewAnyUsers(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/users/for-select?limit=101')
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');
});

it('rejects a negative offset (422)', function () {
    $actor = userWithViewAnyUsers(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/users/for-select?offset=-1')
        ->assertStatus(422)
        ->assertJsonValidationErrors('offset');
});

it('rejects a non-integer id (422)', function () {
    $actor = userWithViewAnyUsers(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/users/for-select?ids[]=abc')
        ->assertStatus(422)
        ->assertJsonValidationErrors('ids.0');
});

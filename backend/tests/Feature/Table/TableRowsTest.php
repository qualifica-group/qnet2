<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Standard users.* permissions + a user granted the requested subset.
 * Mirror of the helper in UserTableConfigTest (guarded for redeclare safety).
 *
 * @param  array<int, string>  $abilities
 */
// Guarded (also declared in TableRowsPersonalDataTest, which the personal-data/
// geo row coverage was split into — file-size split, engineering.md §6).
if (! function_exists('rowsPayload')) {
    function rowsPayload(array $overrides = []): array
    {
        return array_merge([
            'startRow' => 0,
            'endRow' => 25,
        ], $overrides);
    }
}

it('requires authentication on the rows endpoint', function () {
    $this->postJson('/api/tables/users/rows', rowsPayload())->assertUnauthorized();
});

it('returns 404 on the rows endpoint for an unregistered domain (before validation)', function () {
    $user = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($user);

    // Unknown domain must 404 BEFORE validation, even with a malformed payload
    // (404, never 422), AND the body must follow the uniform fail() envelope.
    $this->postJson('/api/tables/nonexistent-domain/rows', ['startRow' => 5, 'endRow' => 0])
        ->assertNotFound()
        ->assertJson(['success' => false])
        ->assertJsonStructure(['success', 'message']);
});

it('returns 403 on rows without users.viewAny', function () {
    $user = userWithUserAbilities([]);
    Sanctum::actingAs($user);

    $this->postJson('/api/tables/users/rows', rowsPayload())->assertForbidden();
});

it('paginates: startRow/endRow map to items and pagination.total', function () {
    $actor = userWithUserAbilities(['viewAny']); // 1 user
    User::factory()->count(9)->create();          // +9 => 10 total
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'startRow' => 0,
        'endRow' => 5,
    ]))->assertOk();

    expect($response->json('items'))->toHaveCount(5)
        ->and($response->json('pagination.total'))->toBe(10)
        ->and($response->json('pagination.offset'))->toBe(0)
        ->and($response->json('pagination.limit'))->toBe(5);

    // Second page returns the remaining rows.
    $page2 = $this->postJson('/api/tables/users/rows', rowsPayload([
        'startRow' => 5,
        'endRow' => 10,
    ]))->assertOk();

    expect($page2->json('items'))->toHaveCount(5)
        ->and($page2->json('pagination.total'))->toBe(10);
});

it('each row carries the contract shape including actions[]', function () {
    $actor = userWithUserAbilities(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload())->assertOk();

    $row = $response->json('items.0');
    expect($row)->toHaveKeys(['id', 'name', 'email', 'avatar_url', 'roles', 'locale', 'created_at', 'actions']);
    // Sensitive fields never leak in a row.
    expect($row)->not->toHaveKey('password')
        ->and($row)->not->toHaveKey('remember_token');
});

it('exposes avatar_url in a row: null without an avatar, a download url with one', function () {
    $actor = userWithUserAbilities(['viewAny', 'view']);
    Storage::fake('local');

    // A user with an avatar and the actor without one.
    $withAvatar = User::factory()->create();
    $withAvatar->attach(UploadedFile::fake()->image('a.png'), User::AVATAR_COLLECTION);

    Sanctum::actingAs($actor);

    $rows = collect($this->postJson('/api/tables/users/rows', rowsPayload(['endRow' => 100]))
        ->assertOk()
        ->json('items'));

    $actorRow = $rows->firstWhere('id', $actor->id);
    $avatarRow = $rows->firstWhere('id', $withAvatar->id);

    expect($actorRow['avatar_url'])->toBeNull()
        ->and($avatarRow['avatar_url'])->toStartWith('data:image/')
        ->and($avatarRow['avatar_url'])->toContain(';base64,');
});

it('sorts on a whitelisted column (name asc)', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $actor->update(['name' => 'Zeb']);
    User::factory()->create(['name' => 'Aaron']);
    User::factory()->create(['name' => 'Mike']);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/users/rows', rowsPayload([
        'sortModel' => [['colId' => 'name', 'sort' => 'asc']],
    ]))->assertOk()->json('items.*.name');

    expect($names)->toBe(['Aaron', 'Mike', 'Zeb']);
});

it('applies a whitelisted text filter (email contains)', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $actor->update(['email' => 'needle@example.com']);
    User::factory()->create(['email' => 'other@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => [
            'email' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'needle'],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.email'))->toBe('needle@example.com');
});

it('applies a whitelisted set filter (roles)', function () {
    Role::create(['name' => 'editor']);
    $actor = userWithUserAbilities(['viewAny']);
    $actor->assignRole('editor');
    User::factory()->count(3)->create(); // no roles
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => [
            'roles' => ['filterType' => 'set', 'values' => ['editor']],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.roles'))->toBe(['editor']);
});

it('returns 422 for a sort colId that is not whitelisted', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/rows', rowsPayload([
        // roles is NOT sortable in the config.
        'sortModel' => [['colId' => 'roles', 'sort' => 'asc']],
    ]))->assertStatus(422)->assertJsonValidationErrors('sortModel.0.colId');
});

it('returns 422 for a filter key that is not whitelisted', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/rows', rowsPayload([
        // avatar_url is NOT filterable in the config (id became filterable
        // under spec 0004, see TableConfigTest / TableRowsTest number-filter
        // coverage).
        'filterModel' => [
            'avatar_url' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'x'],
        ],
    ]))->assertStatus(422)->assertJsonValidationErrors('filterModel.avatar_url');
});

it('applies a whitelisted number filter on id (equals) end-to-end', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $other = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'filterModel' => [
            'id' => ['filterType' => 'number', 'type' => 'equals', 'filter' => $other->id],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($other->id);
});

it('returns 422 when the requested block size exceeds MAX_LIMIT (100)', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/rows', rowsPayload([
        'startRow' => 0,
        'endRow' => 101, // block size 101 > 100
    ]))->assertStatus(422)->assertJsonValidationErrors('endRow');
});

it('returns 422 when endRow is not greater than startRow', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/rows', rowsPayload([
        'startRow' => 10,
        'endRow' => 10,
    ]))->assertStatus(422)->assertJsonValidationErrors('endRow');
});

it('computes per-row actions[] from the actor permissions', function () {
    // Actor with view + update + delete: but no self-delete.
    $actor = userWithUserAbilities(['viewAny', 'view', 'update', 'delete']);
    $other = User::factory()->create();
    Sanctum::actingAs($actor);

    $rows = collect($this->postJson('/api/tables/users/rows', rowsPayload())
        ->assertOk()->json('items'))->keyBy('id');

    // Another row: view + edit + delete allowed.
    expect($rows[$other->id]['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
    // Own row: delete must be excluded (no self-delete), view+edit remain.
    expect($rows[$actor->id]['actions'])->toEqualCanonicalizing(['view', 'edit']);
});

it('limits per-row actions[] to view-only for a read-only actor', function () {
    $actor = userWithUserAbilities(['viewAny', 'view']); // no update/delete
    User::factory()->create();
    Sanctum::actingAs($actor);

    $rows = $this->postJson('/api/tables/users/rows', rowsPayload())->assertOk()->json('items');

    foreach ($rows as $row) {
        expect($row['actions'])->toBe(['view']);
    }
});

// Personal-data/geo-derived row coverage (user_type/primary_address/
// primary_contact/country/region/province/city) lives in
// TableRowsPersonalDataTest.php (file-size split, engineering.md §6).

it('accepts sorting by the injected default id column on a table that does not declare one', function () {
    // tags has no native id column in its catalogue: the injected default
    // (InjectsDefaultIdColumn) must place `id` on the SSRM sort whitelist, so
    // ORDER BY id is accepted (200) rather than rejected (422).
    Permission::findOrCreate('tags.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('tags.viewAny');
    Sanctum::actingAs($actor);

    Tag::factory()->count(3)->create();

    $ids = collect($this->postJson('/api/tables/tags/rows', rowsPayload([
        'sortModel' => [['colId' => 'id', 'sort' => 'desc']],
    ]))->assertOk()->json('data.rows'))->pluck('id')->all();

    expect($ids)->toBe(collect($ids)->sortDesc()->values()->all());
});

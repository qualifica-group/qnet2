<?php

use App\Http\Requests\Table\TableRowsRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Global quick-search (spec 0009): a single grouped OR-LIKE over the
 * definition's server-side `searchableColumnIds()` allow-list
 * (users → name, email). The columns are never taken from the request, and the
 * term is a LIKE-escaped bound parameter — see TableService::applySearch().
 *
 * Helpers are guarded twins of the ones in TableRowsTest (this file is a
 * file-size split and may run in isolation — engineering.md §6).
 *
 * @param  array<int, string>  $abilities
 */
if (! function_exists('userWithUserAbilities')) {
    function userWithUserAbilities(array $abilities): User
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

if (! function_exists('rowsPayload')) {
    function rowsPayload(array $overrides = []): array
    {
        return array_merge([
            'startRow' => 0,
            'endRow' => 25,
        ], $overrides);
    }
}

it('matches on any searchable column (name OR email)', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $actor->update(['name' => 'Zzz Actor', 'email' => 'actor@corp.test']);
    User::factory()->create(['name' => 'Needle Person', 'email' => 'a@other.test']);
    User::factory()->create(['name' => 'Other Person', 'email' => 'needle@match.test']);
    User::factory()->create(['name' => 'Nobody', 'email' => 'x@none.test']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'search' => 'needle',
    ]))->assertOk();

    // One row matched by name, one by email — the OR spans both columns.
    expect($response->json('pagination.total'))->toBe(2)
        ->and($response->json('items.*.name'))
        ->toEqualCanonicalizing(['Needle Person', 'Other Person']);
});

it('AND-combines the search with an active column filter', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $actor->update(['name' => 'Alpha', 'email' => 'alpha@corp.test']);
    User::factory()->create(['name' => 'Bravo', 'email' => 'match@corp.test']);
    User::factory()->create(['name' => 'Charlie', 'email' => 'match@other.test']);
    Sanctum::actingAs($actor);

    // search "match" spans name+email; the email filter narrows it further.
    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'search' => 'match',
        'filterModel' => [
            'email' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'corp'],
        ],
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.email'))->toBe('match@corp.test');
});

it('treats a blank/whitespace search term as no search', function () {
    $actor = userWithUserAbilities(['viewAny']);
    User::factory()->count(4)->create(); // 5 total with the actor
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/rows', rowsPayload([
        'search' => '   ',
    ]))->assertOk();

    expect($response->json('pagination.total'))->toBe(5);
});

it('accepts a search term as long as a full record name (255 chars)', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/rows', rowsPayload([
        'search' => str_repeat('a', TableRowsRequest::SEARCH_MAX_LENGTH),
    ]))->assertOk();
});

it('rejects a search term over the max length (422)', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/rows', rowsPayload([
        'search' => str_repeat('a', TableRowsRequest::SEARCH_MAX_LENGTH + 1),
    ]))->assertStatus(422)->assertJsonValidationErrors('search');
});

it('searches the roles domain on its single searchable column (name)', function () {
    foreach (['viewAny'] as $ability) {
        Permission::findOrCreate("roles.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.viewAny');

    Role::create(['name' => 'needle-editor']);
    Role::create(['name' => 'viewer']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/roles/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'search' => 'needle',
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.name'))->toBe('needle-editor');
});

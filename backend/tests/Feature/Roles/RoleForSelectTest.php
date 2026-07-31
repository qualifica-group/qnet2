<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithRoleAbilities')) {
    /**
     * A non super-admin actor granted exactly the given `roles.*` abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function userWithRoleAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("roles.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("roles.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// Auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/roles/for-select')->assertUnauthorized();
});

it('allows actors without roles.viewAny (200 — ADR 0011 amended)', function () {
    $actor = userWithRoleAbilities([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/roles/for-select')->assertOk();
});

it('allows actors with roles.viewAny (200) and returns the paginated envelope', function () {
    $actor = userWithRoleAbilities(['viewAny']);
    Role::create(['name' => 'editor']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/roles/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ])
        ->assertJsonPath('export_link', null);
});

// ---------------------------------------------------------------------------
// Item shape — minimal { id, label: name } (no subtitle/avatar)
// ---------------------------------------------------------------------------

it('maps a role to { id, label: name } only', function () {
    $actor = userWithRoleAbilities(['viewAny']);
    $role = Role::create(['name' => 'editor']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/roles/for-select?search=editor')->assertOk();

    $item = collect($response->json('items'))->firstWhere('id', $role->id);

    expect($item)->toMatchArray(['id' => $role->id, 'label' => 'editor'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

// ---------------------------------------------------------------------------
// Actor scoping — assignable roles only (privilege coherence)
// ---------------------------------------------------------------------------

it('excludes super-admin for a non super-admin actor', function () {
    $actor = userWithRoleAbilities(['viewAny']);
    Role::create(['name' => 'super-admin']);
    Role::create(['name' => 'editor']);
    Role::create(['name' => 'manager']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/roles/for-select')->assertOk();

    $labels = collect($response->json('items'))->pluck('label');

    expect($labels)->not->toContain('super-admin')
        ->and($labels)->toContain('editor')
        ->and($labels)->toContain('manager');
});

it('includes super-admin for a super-admin actor', function () {
    Role::create(['name' => 'super-admin']);
    Role::create(['name' => 'editor']);
    $actor = User::factory()->create();
    $actor->assignRole('super-admin'); // Gate::before grants roles.viewAny
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/roles/for-select')->assertOk();

    expect(collect($response->json('items'))->pluck('label'))
        ->toContain('super-admin', 'editor');
});

it('does not hydrate a non-assignable role via ids[]', function () {
    $actor = userWithRoleAbilities(['viewAny']);
    $superAdmin = Role::create(['name' => 'super-admin']);
    Role::create(['name' => 'editor']);
    Sanctum::actingAs($actor);

    // Even explicitly requested by id, the privileged role stays out of reach for
    // a non super-admin actor (the badge label still comes from the user resource).
    $response = $this->getJson("/api/roles/for-select?ids[]={$superAdmin->id}")->assertOk();

    expect(collect($response->json('items'))->pluck('id'))->not->toContain($superAdmin->id);
});

// ---------------------------------------------------------------------------
// Search + pagination + validation
// ---------------------------------------------------------------------------

it('searches by name', function () {
    $actor = userWithRoleAbilities(['viewAny']);
    $match = Role::create(['name' => 'auditor']);
    Role::create(['name' => 'editor']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/roles/for-select?search=audit')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

it('defaults to limit 25 when none is given', function () {
    $actor = userWithRoleAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/roles/for-select')
        ->assertOk()
        ->assertJsonPath('pagination.limit', 25);
});

it('rejects a limit above 100 (422)', function () {
    $actor = userWithRoleAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/roles/for-select?limit=101')
        ->assertStatus(422)
        ->assertJsonValidationErrors('limit');
});

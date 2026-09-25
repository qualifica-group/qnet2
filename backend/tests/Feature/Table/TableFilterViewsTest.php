<?php

use App\Models\Role;
use App\Models\TableFilterView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Create the standard users.* permissions and return a freshly-created user
 * granted the requested subset. Guarded so it can coexist with the same helper
 * in the other Table feature test files within one Pest run.
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

if (! function_exists('superAdminUser')) {
    /** A user assigned the privileged super-admin role (Gate::before bypass). */
    function superAdminUser(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }
}

it('lists own views (private + shared) plus other users shared views, excluding others private', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $other = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'users', 'name' => 'Zeta mine', 'visibility' => 'private']);
    TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'users', 'name' => 'Alpha mine', 'visibility' => 'shared']);
    TableFilterView::factory()->create(['user_id' => $other->id, 'domain' => 'users', 'name' => 'Beta shared', 'visibility' => 'shared']);
    TableFilterView::factory()->create(['user_id' => $other->id, 'domain' => 'users', 'name' => 'Hidden private', 'visibility' => 'private']);
    // Different domain: never listed.
    TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'roles', 'name' => 'Other domain', 'visibility' => 'shared']);

    $response = $this->getJson('/api/tables/users/filter-views')->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();

    // Owned first (alpha within group), then shared-by-others (alpha within group).
    expect($names)->toBe(['Alpha mine', 'Zeta mine', 'Beta shared']);

    $byName = collect($response->json('data'))->keyBy('name');
    expect($byName['Alpha mine']['owned'])->toBeTrue()
        ->and($byName['Alpha mine']['owner_name'])->toBeNull()
        ->and($byName['Zeta mine']['owned'])->toBeTrue()
        ->and($byName['Beta shared']['owned'])->toBeFalse()
        ->and($byName['Beta shared']['owner_name'])->toBe($other->name);
});

it('creates a view owned by the actor and returns 201', function () {
    // Spec 0158, D-3: `visibility: shared` now requires
    // `table-filter-views.publish` — granted here so this test still asserts
    // its original intent ("saving a shared view succeeds"), not the new
    // permission gate (covered separately in TableFilterViewFavoritesTest).
    Permission::findOrCreate('table-filter-views.publish');
    $actor = userWithUserAbilities(['viewAny']);
    $actor->givePermissionTo('table-filter-views.publish');
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/filter-views', [
        'name' => 'Active admins',
        'filters' => ['name' => ['filterType' => 'text', 'filter' => 'John']],
        'visibility' => 'shared',
    ])->assertCreated();

    $response->assertJsonPath('data.name', 'Active admins')
        ->assertJsonPath('data.visibility', 'shared')
        ->assertJsonPath('data.owned', true)
        ->assertJsonPath('data.owner_name', null)
        ->assertJsonPath('data.filters.name.filter', 'John');

    $this->assertDatabaseHas('table_filter_views', [
        'user_id' => $actor->id,
        'domain' => 'users',
        'name' => 'Active admins',
        'visibility' => 'shared',
    ]);
});

it('rejects a duplicate view name for the same user and domain with 422', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'users', 'name' => 'My view']);

    $this->postJson('/api/tables/users/filter-views', [
        'name' => 'My view',
        'filters' => [],
        'visibility' => 'private',
    ])->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('allows the same view name for different users or different domains', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'roles', 'name' => 'My view']);

    $this->postJson('/api/tables/users/filter-views', [
        'name' => 'My view',
        'filters' => [],
        'visibility' => 'private',
    ])->assertCreated();
});

it('rejects a filters key outside the filterable allow-list with 422', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $this->postJson('/api/tables/users/filter-views', [
        'name' => 'Ghost filter',
        'filters' => ['ghost' => ['filterType' => 'text', 'filter' => 'x']],
        'visibility' => 'private',
    ])->assertUnprocessable()->assertJsonValidationErrors('filters.ghost');
});

it('lets the owner update their view', function () {
    // Spec 0158, D-3: the update below sets `visibility: shared`, so the
    // actor needs `table-filter-views.publish` (see the store test above).
    Permission::findOrCreate('table-filter-views.publish');
    $actor = userWithUserAbilities(['viewAny']);
    $actor->givePermissionTo('table-filter-views.publish');
    Sanctum::actingAs($actor);

    $view = TableFilterView::factory()->create([
        'user_id' => $actor->id,
        'domain' => 'users',
        'name' => 'Old name',
        'filters' => [],
        'visibility' => 'private',
    ]);

    $this->putJson("/api/tables/users/filter-views/{$view->id}", [
        'name' => 'New name',
        'filters' => ['roles' => ['filterType' => 'set', 'values' => ['admin']]],
        'visibility' => 'shared',
    ])->assertOk()
        ->assertJsonPath('data.name', 'New name')
        ->assertJsonPath('data.visibility', 'shared');

    $this->assertDatabaseHas('table_filter_views', [
        'id' => $view->id,
        'name' => 'New name',
        'visibility' => 'shared',
    ]);
});

it('forbids a non-owner from updating another users view', function () {
    $owner = userWithUserAbilities(['viewAny']);
    $other = userWithUserAbilities(['viewAny']);

    $view = TableFilterView::factory()->create([
        'user_id' => $owner->id,
        'domain' => 'users',
        'visibility' => 'shared',
    ]);

    Sanctum::actingAs($other);

    $this->putJson("/api/tables/users/filter-views/{$view->id}", [
        'name' => 'Hijacked',
        'filters' => [],
        'visibility' => 'private',
    ])->assertForbidden();
});

it('lets a super-admin update and delete another users shared view', function () {
    $owner = userWithUserAbilities(['viewAny']);
    $admin = superAdminUser();

    $view = TableFilterView::factory()->create([
        'user_id' => $owner->id,
        'domain' => 'users',
        'name' => 'Shared by owner',
        'visibility' => 'shared',
    ]);

    Sanctum::actingAs($admin);

    $this->putJson("/api/tables/users/filter-views/{$view->id}", [
        'name' => 'Edited by admin',
        'filters' => [],
        'visibility' => 'shared',
    ])->assertOk()->assertJsonPath('data.name', 'Edited by admin');

    $this->deleteJson("/api/tables/users/filter-views/{$view->id}")->assertNoContent();

    $this->assertDatabaseMissing('table_filter_views', ['id' => $view->id]);
});

it('lets the owner delete their view', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $view = TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'users']);

    $this->deleteJson("/api/tables/users/filter-views/{$view->id}")->assertNoContent();

    $this->assertDatabaseMissing('table_filter_views', ['id' => $view->id]);
});

it('forbids a non-owner from deleting another users view', function () {
    $owner = userWithUserAbilities(['viewAny']);
    $other = userWithUserAbilities(['viewAny']);

    $view = TableFilterView::factory()->create([
        'user_id' => $owner->id,
        'domain' => 'users',
        'visibility' => 'shared',
    ]);

    Sanctum::actingAs($other);

    $this->deleteJson("/api/tables/users/filter-views/{$view->id}")->assertForbidden();

    $this->assertDatabaseHas('table_filter_views', ['id' => $view->id]);
});

it('returns 404 for a bound filterView whose domain does not match the route domain', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $view = TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'roles']);

    $this->putJson("/api/tables/users/filter-views/{$view->id}", [
        'name' => 'x',
        'filters' => [],
        'visibility' => 'private',
    ])->assertNotFound();

    $this->deleteJson("/api/tables/users/filter-views/{$view->id}")->assertNotFound();
});

it('returns 404 for filter-views on an unregistered domain', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $this->getJson('/api/tables/nonexistent-domain/filter-views')->assertNotFound()->assertJson(['success' => false]);
    $this->postJson('/api/tables/nonexistent-domain/filter-views', [
        'name' => 'x', 'filters' => [], 'visibility' => 'private',
    ])->assertNotFound();
});

it('requires authentication for every filter-views endpoint', function () {
    $this->getJson('/api/tables/users/filter-views')->assertUnauthorized();
    $this->postJson('/api/tables/users/filter-views', ['name' => 'x', 'filters' => [], 'visibility' => 'private'])
        ->assertUnauthorized();
    $this->putJson('/api/tables/users/filter-views/1', ['name' => 'x', 'filters' => [], 'visibility' => 'private'])
        ->assertUnauthorized();
    $this->deleteJson('/api/tables/users/filter-views/1')->assertUnauthorized();
});

it('returns 403 for list/create without users.viewAny', function () {
    Sanctum::actingAs(userWithUserAbilities([])); // no abilities

    $this->getJson('/api/tables/users/filter-views')->assertForbidden();
    $this->postJson('/api/tables/users/filter-views', [
        'name' => 'x', 'filters' => [], 'visibility' => 'private',
    ])->assertForbidden();
});

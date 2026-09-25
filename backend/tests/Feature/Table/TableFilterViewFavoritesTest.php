<?php

use App\Models\Role;
use App\Models\TableFilterView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Publish permission (spec 0158, D-3, AC-004) and per-user favorites
 * (spec 0158, D-4, AC-005) on saved filter views.
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
    /** Duplicated (guarded) across the suites that need it — see TableFilterViewsTest. */
    function superAdminUser(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }
}

function userWithPublishAbility(): User
{
    Permission::findOrCreate('table-filter-views.publish');
    $actor = userWithUserAbilities(['viewAny']);
    $actor->givePermissionTo('table-filter-views.publish');

    return $actor;
}

// ---------------------------------------------------------------------------
// AC-004 — publish permission
// ---------------------------------------------------------------------------

it('AC-004: 403 creating a shared view without table-filter-views.publish', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $this->postJson('/api/tables/users/filter-views', [
        'name' => 'No permission', 'filters' => [], 'visibility' => 'shared',
    ])->assertForbidden();
});

it('AC-004: 201 creating a shared view with the permission, visible to other users', function () {
    $actor = userWithPublishAbility();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/filter-views', [
        'name' => 'Published', 'filters' => [], 'visibility' => 'shared',
    ])->assertCreated()->assertJsonPath('data.visibility', 'shared');

    $other = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($other);

    $names = collect($this->getJson('/api/tables/users/filter-views')->assertOk()->json('data'))->pluck('name')->all();
    expect($names)->toContain('Published');
});

it('AC-004: 403 updating a private view to shared without the permission', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $view = TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'users', 'visibility' => 'private']);

    $this->putJson("/api/tables/users/filter-views/{$view->id}", [
        'name' => 'Still private?', 'filters' => [], 'visibility' => 'shared',
    ])->assertForbidden();
});

it('AC-004: a super-admin publishes a shared view without holding the permission (Gate::before)', function () {
    Sanctum::actingAs(superAdminUser());

    $this->postJson('/api/tables/users/filter-views', [
        'name' => 'Admin shared', 'filters' => [], 'visibility' => 'shared',
    ])->assertCreated();
});

it('private visibility never requires the publish permission', function () {
    Sanctum::actingAs(userWithUserAbilities(['viewAny']));

    $this->postJson('/api/tables/users/filter-views', [
        'name' => 'Private ok', 'filters' => [], 'visibility' => 'private',
    ])->assertCreated();
});

// ---------------------------------------------------------------------------
// AC-005 — favorites
// ---------------------------------------------------------------------------

it('AC-005: favorites sort first (name asc), then own, then shared-by-others; unfavoriting restores the original slot', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $other = userWithUserAbilities(['viewAny']);

    $zulu = TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'users', 'name' => 'Zulu', 'visibility' => 'private']);
    $alpha = TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'users', 'name' => 'Alpha', 'visibility' => 'private']);
    TableFilterView::factory()->create(['user_id' => $other->id, 'domain' => 'users', 'name' => 'Yankee', 'visibility' => 'shared']);
    $bravo = TableFilterView::factory()->create(['user_id' => $other->id, 'domain' => 'users', 'name' => 'Bravo', 'visibility' => 'shared']);

    Sanctum::actingAs($actor);

    $listNames = fn () => collect($this->getJson('/api/tables/users/filter-views')->assertOk()->json('data'))->pluck('name')->all();

    expect($listNames())->toBe(['Alpha', 'Zulu', 'Bravo', 'Yankee']);

    $this->postJson("/api/tables/users/filter-views/{$zulu->id}/favorite")
        ->assertOk()
        ->assertJsonPath('data.is_favorite', true);

    expect($listNames())->toBe(['Zulu', 'Alpha', 'Bravo', 'Yankee']);

    $this->postJson("/api/tables/users/filter-views/{$bravo->id}/favorite")->assertOk();

    expect($listNames())->toBe(['Bravo', 'Zulu', 'Alpha', 'Yankee']);

    $this->deleteJson("/api/tables/users/filter-views/{$zulu->id}/favorite")
        ->assertOk()
        ->assertJsonPath('data.is_favorite', false);

    // Zulu returns to the "own" group, sorted with Alpha — its original slot.
    expect($listNames())->toBe(['Bravo', 'Alpha', 'Zulu', 'Yankee']);
});

it('AC-005: a user never sees another users favorite flag', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $viewer = userWithUserAbilities(['viewAny']);

    $view = TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'users', 'name' => 'Shared one', 'visibility' => 'shared']);

    Sanctum::actingAs($actor);
    $this->postJson("/api/tables/users/filter-views/{$view->id}/favorite")->assertOk();

    Sanctum::actingAs($viewer);
    $data = collect($this->getJson('/api/tables/users/filter-views')->assertOk()->json('data'))->keyBy('name');
    expect($data['Shared one']['is_favorite'])->toBeFalse();
});

it('favorite/unfavorite are idempotent', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $view = TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'users']);

    $this->postJson("/api/tables/users/filter-views/{$view->id}/favorite")->assertOk();
    $this->postJson("/api/tables/users/filter-views/{$view->id}/favorite")->assertOk();

    $this->assertDatabaseCount('table_filter_view_favorites', 1);

    $this->deleteJson("/api/tables/users/filter-views/{$view->id}/favorite")->assertOk();
    $this->deleteJson("/api/tables/users/filter-views/{$view->id}/favorite")->assertOk();

    $this->assertDatabaseCount('table_filter_view_favorites', 0);
});

it('favoriting a view of another domain 404s', function () {
    $actor = userWithUserAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $view = TableFilterView::factory()->create(['user_id' => $actor->id, 'domain' => 'roles']);

    $this->postJson("/api/tables/users/filter-views/{$view->id}/favorite")->assertNotFound();
});

it('favoriting another users PRIVATE view 404s', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $owner = userWithUserAbilities(['viewAny']);

    $view = TableFilterView::factory()->create(['user_id' => $owner->id, 'domain' => 'users', 'visibility' => 'private']);

    Sanctum::actingAs($actor);

    $this->postJson("/api/tables/users/filter-views/{$view->id}/favorite")->assertNotFound();
});

it('favoriting another users SHARED view succeeds', function () {
    $actor = userWithUserAbilities(['viewAny']);
    $owner = userWithUserAbilities(['viewAny']);

    $view = TableFilterView::factory()->create(['user_id' => $owner->id, 'domain' => 'users', 'visibility' => 'shared']);

    Sanctum::actingAs($actor);

    $this->postJson("/api/tables/users/filter-views/{$view->id}/favorite")
        ->assertOk()
        ->assertJsonPath('data.is_favorite', true);
});

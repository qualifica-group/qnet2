<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Standard users.* permissions + a user granted the requested subset. Mirror
 * of the helper already declared in the sibling Table/Users test suites
 * (guarded for redeclare safety).
 *
 * @param  array<int, string>  $abilities
 */
it('requires authentication', function () {
    $this->postJson('/api/tables/users/bulk-delete', ['ids' => [1]])->assertUnauthorized();
});

it('returns 404 for an unregistered domain, before ids validation', function () {
    $actor = userWithUserAbilities(['viewAny', 'delete']);
    Sanctum::actingAs($actor);

    // Malformed payload (empty ids) AND unknown domain: 404 wins, never 422.
    $this->postJson('/api/tables/nonexistent-domain/bulk-delete', ['ids' => []])
        ->assertNotFound()
        ->assertJson(['success' => false])
        ->assertJsonStructure(['success', 'message']);
});

it('returns 403 without users.viewAny', function () {
    $actor = userWithUserAbilities([]);
    Sanctum::actingAs($actor);

    $target = User::factory()->create();

    $this->postJson('/api/tables/users/bulk-delete', ['ids' => [$target->id]])
        ->assertForbidden();
});

it('returns 422 when ids is missing or empty', function () {
    $actor = userWithUserAbilities(['viewAny', 'delete']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/bulk-delete', [])->assertUnprocessable();
    $this->postJson('/api/tables/users/bulk-delete', ['ids' => []])->assertUnprocessable();
});

it('happy path: deletes every id and reports the summary envelope', function () {
    $actor = userWithUserAbilities(['viewAny', 'delete']);
    $targets = User::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/bulk-delete', [
        'ids' => $targets->pluck('id')->all(),
    ])->assertOk();

    $response->assertJson([
        'success' => true,
        'data' => ['deleted' => 3, 'failed' => []],
    ]);

    foreach ($targets as $target) {
        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }
});

it('skips ids the actor cannot delete (no users.delete): all forbidden, none removed', function () {
    $actor = userWithUserAbilities(['viewAny']); // no delete
    $targets = User::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $ids = $targets->pluck('id')->all();

    $response = $this->postJson('/api/tables/users/bulk-delete', ['ids' => $ids])
        ->assertOk();

    expect($response->json('data.deleted'))->toBe(0)
        ->and($response->json('data.failed'))->toHaveCount(2);

    foreach ($response->json('data.failed') as $entry) {
        expect($entry['reason'])->toBe('forbidden');
    }

    foreach ($targets as $target) {
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }
});

it('self-delete is skipped (forbidden, mirroring the single DELETE endpoint), others still removed', function () {
    $actor = userWithUserAbilities(['viewAny', 'delete']);
    $other = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/bulk-delete', [
        'ids' => [$actor->id, $other->id],
    ])->assertOk();

    expect($response->json('data.deleted'))->toBe(1);

    $failed = collect($response->json('data.failed'));
    expect($failed)->toHaveCount(1)
        ->and($failed->first()['id'])->toBe($actor->id)
        ->and($failed->first()['reason'])->toBe('forbidden');

    $this->assertDatabaseHas('users', ['id' => $actor->id]);
    $this->assertDatabaseMissing('users', ['id' => $other->id]);
});

it('guards the last super-admin: that id is guarded, others still removed', function () {
    Role::create(['name' => 'super-admin']);

    $lastSuperAdmin = User::factory()->create();
    $lastSuperAdmin->assignRole('super-admin');

    $actor = userWithUserAbilities(['viewAny', 'delete']);
    $other = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/bulk-delete', [
        'ids' => [$lastSuperAdmin->id, $other->id],
    ])->assertOk();

    expect($response->json('data.deleted'))->toBe(1);

    $failed = collect($response->json('data.failed'));
    expect($failed)->toHaveCount(1)
        ->and($failed->first()['id'])->toBe($lastSuperAdmin->id)
        ->and($failed->first()['reason'])->toBe('guarded');

    $this->assertDatabaseHas('users', ['id' => $lastSuperAdmin->id]);
    $this->assertDatabaseMissing('users', ['id' => $other->id]);
});

it('reports an id outside the base scope (nonexistent) as not_found', function () {
    $actor = userWithUserAbilities(['viewAny', 'delete']);
    $existing = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/users/bulk-delete', [
        'ids' => [$existing->id, 999999],
    ])->assertOk();

    expect($response->json('data.deleted'))->toBe(1);

    $failed = collect($response->json('data.failed'));
    expect($failed)->toHaveCount(1)
        ->and($failed->first()['id'])->toBe(999999)
        ->and($failed->first()['reason'])->toBe('not_found');
});

it('guards the protected super-admin role on the roles domain', function () {
    Permission::findOrCreate('roles.viewAny');
    Permission::findOrCreate('roles.delete');

    $superAdminRole = Role::create(['name' => 'super-admin']);
    $editorRole = Role::create(['name' => 'editor']);

    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.viewAny', 'roles.delete');
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/roles/bulk-delete', [
        'ids' => [$superAdminRole->id, $editorRole->id],
    ])->assertOk();

    expect($response->json('data.deleted'))->toBe(1);

    $failed = collect($response->json('data.failed'));
    expect($failed)->toHaveCount(1)
        ->and($failed->first()['id'])->toBe($superAdminRole->id)
        ->and($failed->first()['reason'])->toBe('guarded');

    $this->assertDatabaseHas('roles', ['id' => $superAdminRole->id]);
    $this->assertDatabaseMissing('roles', ['id' => $editorRole->id]);
});

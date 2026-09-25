<?php

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC 4 — GET /api/users/{user}: all six flags per field, hidden/readonly derivation
// ---------------------------------------------------------------------------

it('users show: every field emits all six flags with correct hidden/readonly derivation', function () {
    $actor = userWithUserAbilities(['view', 'update']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/{$target->id}")->assertOk();

    $fields = $response->json('permissions.fields');
    expect($fields)->not->toBeEmpty();

    foreach ($fields as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled'])
            ->and($field['hidden'])->toBe(! $field['visible'])
            ->and($field['readonly'])->toBe($field['visible'] && ! $field['editable'] && ! $field['disabled']);
    }
});

// ---------------------------------------------------------------------------
// AC 5 — non-super-admin editing a super-admin user: roles locked
// ---------------------------------------------------------------------------

it('non-super-admin actor editing a super-admin user: fields.roles is locked readonly', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = userWithUserAbilities(['view', 'update']);
    $target = User::factory()->create();
    $target->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.roles.editable', false)
        ->assertJsonPath('permissions.fields.roles.readonly', true);
});

it('super-admin actor editing a super-admin user: fields.roles stays editable', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = User::factory()->create();
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    $target = User::factory()->create();
    $target->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.roles.editable', true)
        ->assertJsonPath('permissions.fields.roles.readonly', false);
});

// ---------------------------------------------------------------------------
// AC 6 — GET /api/roles/{superAdminRole}
// ---------------------------------------------------------------------------

it('non-super-admin actor: super-admin role name/permissions locked readonly', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = actorWithRoleAbilities(['view', 'update']);
    $target = Role::where('name', RoleAssignmentGuard::PRIVILEGED_ROLE)->first();
    Sanctum::actingAs($actor);

    $this->getJson("/api/roles/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', false)
        ->assertJsonPath('permissions.fields.permissions.editable', false);
});

it('super-admin actor: super-admin role name/permissions are editable', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = User::factory()->create();
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    $target = Role::where('name', RoleAssignmentGuard::PRIVILEGED_ROLE)->first();
    Sanctum::actingAs($actor);

    $this->getJson("/api/roles/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', true)
        ->assertJsonPath('permissions.fields.permissions.editable', true);
});

// ---------------------------------------------------------------------------
// AC 7 — actions.delete false on self / on the super-admin role
// ---------------------------------------------------------------------------

it('users: actions.delete is false when acting on self', function () {
    $actor = userWithUserAbilities(['view', 'delete']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$actor->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false);
});

it('users: actions.delete is true on another user with users.delete', function () {
    $actor = userWithUserAbilities(['view', 'delete']);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/users/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', true);
});

it('roles: actions.delete is false on the super-admin role even with roles.delete', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = actorWithRoleAbilities(['view', 'delete']);
    $target = Role::where('name', RoleAssignmentGuard::PRIVILEGED_ROLE)->first();
    Sanctum::actingAs($actor);

    $this->getJson("/api/roles/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false);
});

it('roles: actions.delete is true on a normal role with roles.delete', function () {
    $actor = actorWithRoleAbilities(['view', 'delete']);
    $target = Role::create(['name' => 'editor']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/roles/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', true);
});

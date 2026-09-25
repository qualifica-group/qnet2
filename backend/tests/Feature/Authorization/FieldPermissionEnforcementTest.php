<?php

use App\Models\PersonalData;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC 8 — submitting a non-editable field → 422, keyed on the field, no write
// ---------------------------------------------------------------------------

it('users update: 422 on a non-editable field (roles locked on a super-admin target), no write', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $editor = Role::create(['name' => 'editor']);
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create();
    $target->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['roles' => [$editor->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('roles');

    expect($target->fresh()->hasRole('editor'))->toBeFalse()
        ->and($target->fresh()->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE))->toBeTrue();
});

it('roles update: 422 on a non-editable field (name locked on the super-admin role), no write', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = actorWithRoleAbilities(['update']);
    $target = Role::where('name', RoleAssignmentGuard::PRIVILEGED_ROLE)->first();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$target->id}", ['name' => 'hacked'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');

    $this->assertDatabaseHas('roles', ['id' => $target->id, 'name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
});

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    // The actor has NO users.update ability at all: the request must fail with
    // the controller's 403, not a field-level 422 from the FormRequest layer.
    $actor = userWithUserAbilities([]);
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['locale' => 'it'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC 9 — submitting only editable fields → 200/persisted
// ---------------------------------------------------------------------------

it('users update: 200 and persists when submitting only editable fields', function () {
    $actor = userWithUserAbilities(['update']);
    $target = User::factory()->create(['locale' => 'en']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['locale' => 'it'])->assertOk();

    $this->assertDatabaseHas('users', ['id' => $target->id, 'locale' => 'it']);
});

it('roles update: 200 and persists when submitting only editable fields', function () {
    $actor = actorWithRoleAbilities(['update']);
    $target = Role::create(['name' => 'before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$target->id}", ['name' => 'after'])->assertOk();

    $this->assertDatabaseHas('roles', ['id' => $target->id, 'name' => 'after']);
});

// ---------------------------------------------------------------------------
// AC 10 — editable-but-value-guarded field still 422 (composition, not regression)
// ---------------------------------------------------------------------------

it('users update: editable roles field, but a non-assignable role id is still rejected (422)', function () {
    $superAdmin = Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = userWithUserAbilities(['update']); // not a super-admin
    $target = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", ['roles' => [$superAdmin->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('roles.0');

    expect($target->fresh()->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE))->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC6-literal vs RoleService::guardSystemRoleMutation — metadata says editable
// for a super-admin actor, but the SERVICE guard is the unconditional final
// authority and still blocks the mutation (composition, not regression).
// ---------------------------------------------------------------------------

it('roles update: super-admin actor — metadata says editable, but guardSystemRoleMutation still blocks the write (422)', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $actor = User::factory()->create();
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE); // Gate::before grants roles.update
    $target = Role::where('name', RoleAssignmentGuard::PRIVILEGED_ROLE)->first();
    Sanctum::actingAs($actor);

    // AC6 (literal): the metadata reports name/permissions as editable=true
    // for a super-admin actor on the super-admin role, so
    // EnforcesFieldPermissions lets the submission through the FormRequest.
    $metadata = $this->getJson("/api/roles/{$target->id}")->assertOk();
    expect($metadata->json('permissions.fields.name.editable'))->toBeTrue()
        ->and($metadata->json('permissions.fields.permissions.editable'))->toBeTrue();

    // RoleService::guardSystemRoleMutation is the unconditional final guard
    // (applies even to a super-admin actor — the super-admin role must always
    // retain every permission) and still rejects the actual write.
    $this->patchJson("/api/roles/{$target->id}", ['name' => 'hacked'])
        ->assertStatus(422);

    $this->assertDatabaseHas('roles', ['id' => $target->id, 'name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
});

// ---------------------------------------------------------------------------
// Spec 0006 AC 9 — the write path honors the DB field-permission matrix via
// the SAME EnforcesFieldPermissions trait (no new code path): a role-level
// `editable:false` row rejects the write exactly like a ceiling-level lock.
//
// NOTE (spec 0008 mandatory bypass): retargeted from `locale` (now mandatory,
// so a DB row on it is bypassed entirely and could never 422) onto the
// non-mandatory `personal_data.tax_code`, nested in the profile payload.
// ---------------------------------------------------------------------------

it('spec 0006: users.personal_data.tax_code editable:false for the actor\'s role → 422 "field not editable", no write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("users.{$ability}");
    }

    $role = Role::create(['name' => 'tax-code-locked']);
    $role->givePermissionTo(['users.view', 'users.update']);
    $role->fieldPermissions()->create([
        'resource' => 'users',
        'field' => 'personal_data.tax_code',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = User::factory()->create();
    PersonalData::factory()->individual()->for($target, 'personable')->create(['tax_code' => 'LVLDAA80A01H501V']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'personal_data' => ['type' => 'individual', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'tax_code' => 'LVLDAA85A01H501A'],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.tax_code');

    $this->assertDatabaseHas('personal_data', ['personable_id' => $target->id, 'tax_code' => 'LVLDAA80A01H501V']);
});

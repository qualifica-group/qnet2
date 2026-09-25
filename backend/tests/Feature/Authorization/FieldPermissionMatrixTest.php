<?php

use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Models\RoleFieldPermission;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC 1 — migration + model: a role can have role_field_permissions rows;
// unique (role_id, resource, field) enforced.
// ---------------------------------------------------------------------------

it('a role can have role_field_permissions rows', function () {
    $role = Role::create(['name' => 'matrix-role']);

    $row = $role->fieldPermissions()->create([
        'resource' => 'users',
        'field' => 'email',
        'visible' => false,
        'editable' => true,
        'required' => false,
    ]);

    expect($row)->toBeInstanceOf(RoleFieldPermission::class)
        ->and($role->fieldPermissions()->count())->toBe(1);

    $this->assertDatabaseHas('role_field_permissions', [
        'role_id' => $role->id,
        'resource' => 'users',
        'field' => 'email',
        'visible' => false,
        'editable' => true,
        'required' => false,
    ]);
});

it('a row resolves back to its owning role via the inverse belongsTo relation', function () {
    $role = Role::create(['name' => 'inverse-relation']);
    $row = $role->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false]);

    expect($row->role)->toBeInstanceOf(Role::class)
        ->and($row->role->id)->toBe($role->id);
});

it('RoleResource reads field_permissions from an already-eager-loaded relation without a second query', function () {
    $role = Role::create(['name' => 'eager-loaded']);
    $role->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false]);

    $loaded = Role::with('fieldPermissions')->findOrFail($role->id);

    $array = (new RoleResource($loaded))->toArray(Request::create('/'));

    expect($array['field_permissions'])->toBe([
        ['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false],
    ]);
});

it('enforces unique (role_id, resource, field)', function () {
    $role = Role::create(['name' => 'matrix-unique']);
    $role->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false]);

    expect(fn () => $role->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => true, 'editable' => true, 'required' => false]))
        ->toThrow(QueryException::class);
});

it('cascades delete: removing the role removes its field-permission rows', function () {
    $role = Role::create(['name' => 'cascade-role']);
    $row = $role->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false]);

    $role->delete();

    $this->assertDatabaseMissing('role_field_permissions', ['id' => $row->id]);
});

// ---------------------------------------------------------------------------
// AC 3 — create/update role with field_permissions persists; [] clears;
// omitted key leaves untouched.
// ---------------------------------------------------------------------------

it('create role: field_permissions persists the rows', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', [
        'name' => 'created-with-matrix',
        'field_permissions' => [
            ['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false],
            ['resource' => 'roles', 'field' => 'name', 'visible' => true, 'editable' => false, 'required' => false],
        ],
    ])->assertCreated();

    $role = Role::where('name', 'created-with-matrix')->firstOrFail();

    expect($role->fieldPermissions)->toHaveCount(2);
    $this->assertDatabaseHas('role_field_permissions', ['role_id' => $role->id, 'resource' => 'users', 'field' => 'email', 'visible' => false]);
    $this->assertDatabaseHas('role_field_permissions', ['role_id' => $role->id, 'resource' => 'roles', 'field' => 'name', 'editable' => false]);
});

it('create role: response echoes field_permissions', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/roles', [
        'name' => 'echo-matrix',
        'field_permissions' => [
            ['resource' => 'users', 'field' => 'locale', 'visible' => true, 'editable' => false, 'required' => false],
        ],
    ])->assertCreated();

    expect($response->json('data.field_permissions'))->toEqual([
        ['resource' => 'users', 'field' => 'locale', 'visible' => true, 'editable' => false, 'required' => false],
    ]);
});

it('update role: field_permissions replaces the existing matrix', function () {
    $actor = actorWithRoleAbilities(['update']);
    $role = Role::create(['name' => 'replace-matrix']);
    $role->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$role->id}", [
        'field_permissions' => [
            ['resource' => 'users', 'field' => 'locale', 'visible' => false, 'editable' => false, 'required' => false],
        ],
    ])->assertOk();

    $role->refresh();
    expect($role->fieldPermissions)->toHaveCount(1)
        ->and($role->fieldPermissions->first()->field)->toBe('locale');
    $this->assertDatabaseMissing('role_field_permissions', ['role_id' => $role->id, 'field' => 'email']);
});

it('update role: field_permissions [] clears the matrix', function () {
    $actor = actorWithRoleAbilities(['update']);
    $role = Role::create(['name' => 'clear-matrix']);
    $role->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$role->id}", ['field_permissions' => []])->assertOk();

    expect($role->fieldPermissions()->count())->toBe(0);
});

it('update role: omitting field_permissions leaves the matrix untouched', function () {
    $actor = actorWithRoleAbilities(['update']);
    $role = Role::create(['name' => 'untouched-matrix']);
    $role->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/roles/{$role->id}", ['name' => 'untouched-matrix-renamed'])->assertOk();

    expect($role->fieldPermissions()->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// AC 4 — validation: unknown resource / unknown field / non-boolean flag → 422.
// ---------------------------------------------------------------------------

it('422: unknown resource in field_permissions', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', [
        'name' => 'bad-resource',
        'field_permissions' => [
            ['resource' => 'ghost-resource', 'field' => 'email', 'visible' => true, 'editable' => true, 'required' => false],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('field_permissions.0.resource');

    $this->assertDatabaseMissing('roles', ['name' => 'bad-resource']);
});

it('422: field not in that resource\'s catalogue', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', [
        'name' => 'bad-field',
        'field_permissions' => [
            ['resource' => 'users', 'field' => 'not_a_real_field', 'visible' => true, 'editable' => true, 'required' => false],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('field_permissions.0.field');

    $this->assertDatabaseMissing('roles', ['name' => 'bad-field']);
});

it('422: a field that belongs to a DIFFERENT resource is still rejected', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    // "permissions" is a real roles field, not a users field.
    $this->postJson('/api/roles', [
        'name' => 'cross-resource-field',
        'field_permissions' => [
            ['resource' => 'users', 'field' => 'permissions', 'visible' => true, 'editable' => true, 'required' => false],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('field_permissions.0.field');
});

it('422: a missing resource is flagged once by the base rule, with no crash from the cross-check', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', [
        'name' => 'missing-resource',
        'field_permissions' => [
            ['field' => 'email', 'visible' => true, 'editable' => true, 'required' => false],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('field_permissions.0.resource');

    $this->assertDatabaseMissing('roles', ['name' => 'missing-resource']);
});

// ---------------------------------------------------------------------------
// AC-003 (spec 0008) — the new personal_data.* keys are valid `users` catalogue
// entries; an unknown key is still rejected.
// ---------------------------------------------------------------------------

it('spec 0008: field_permissions accepts users.personal_data.first_name (valid catalogue key)', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', [
        'name' => 'personal-data-matrix',
        'field_permissions' => [
            ['resource' => 'users', 'field' => 'personal_data.first_name', 'visible' => true, 'editable' => false, 'required' => false],
        ],
    ])->assertCreated();

    $role = Role::where('name', 'personal-data-matrix')->firstOrFail();
    $this->assertDatabaseHas('role_field_permissions', [
        'role_id' => $role->id,
        'resource' => 'users',
        'field' => 'personal_data.first_name',
        'editable' => false,
    ]);
});

it('spec 0008: field_permissions rejects a non-existent users field (users.foo)', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', [
        'name' => 'bad-personal-data-field',
        'field_permissions' => [
            ['resource' => 'users', 'field' => 'foo', 'visible' => true, 'editable' => true, 'required' => false],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('field_permissions.0.field');

    $this->assertDatabaseMissing('roles', ['name' => 'bad-personal-data-field']);
});

it('422: non-boolean flag', function () {
    $actor = actorWithRoleAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/roles', [
        'name' => 'bad-flag',
        'field_permissions' => [
            ['resource' => 'users', 'field' => 'email', 'visible' => 'yes', 'editable' => true, 'required' => false],
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('field_permissions.0.visible');

    $this->assertDatabaseMissing('roles', ['name' => 'bad-flag']);
});

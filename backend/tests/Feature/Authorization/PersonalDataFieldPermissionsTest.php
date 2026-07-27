<?php

use App\Models\PersonalData;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithUserAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithUserAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("users.{$ability}");
        }

        return $user;
    }
}

/**
 * A role with the given `users.*` abilities and an optional single
 * role_field_permissions row, assigned to a fresh actor.
 *
 * @param  array<int, string>  $abilities
 * @param  array<string, mixed>|null  $matrixRow
 */
if (! function_exists('actorWithFieldPermissionRole')) {
    function actorWithFieldPermissionRole(array $abilities, ?array $matrixRow = null): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $role = Role::create(['name' => 'field-perm-role-'.uniqid()]);
        $role->givePermissionTo(array_map(static fn (string $ability): string => "users.{$ability}", $abilities));

        if ($matrixRow !== null) {
            $role->fieldPermissions()->create($matrixRow);
        }

        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

// ---------------------------------------------------------------------------
// AC-002 — permissions.fields includes the 12 personal_data.* keys, ceiling
// editable by default for an actor who may update, readonly otherwise.
//
// NOTE: each key IS the flat JSON property name (e.g. "personal_data.tax_code"),
// not a nested path — assertJsonPath()/json('a.b') would misparse the literal
// dot as nesting, so these tests read `permissions.fields` as a plain array
// and index it directly with the full key string.
// ---------------------------------------------------------------------------

it('AC-002: permissions.fields includes the 12 personal_data.* keys, editable when the actor may update', function () {
    $actor = userWithUserAbilities(['view', 'update']);
    $target = User::factory()->create();
    PersonalData::factory()->for($target, 'personable')->create();
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/users/{$target->id}")->assertOk();
    $fields = $response->json('permissions.fields');

    $expectedKeys = [
        'personal_data.type', 'personal_data.first_name',
        'personal_data.last_name', 'personal_data.company_name', 'personal_data.tax_code',
        'personal_data.vat_number', 'personal_data.sdi_code', 'personal_data.birth_date',
        'personal_data.birth_city_id',
        'personal_data.contacts', 'personal_data.addresses',
    ];

    foreach ($expectedKeys as $key) {
        expect($fields)->toHaveKey($key);
        expect($fields[$key])->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled'])
            ->and($fields[$key]['visible'])->toBeTrue()
            ->and($fields[$key]['editable'])->toBeTrue()
            ->and($fields[$key]['required'])->toBeFalse();
    }
});

it('AC-002: permissions.fields personal_data.* are visibleReadonly when the actor may NOT update', function () {
    $actor = userWithUserAbilities(['view']);
    $target = User::factory()->create();
    PersonalData::factory()->for($target, 'personable')->create();
    Sanctum::actingAs($actor);

    $fields = $this->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.fields');

    expect($fields['personal_data.tax_code']['editable'])->toBeFalse()
        ->and($fields['personal_data.tax_code']['readonly'])->toBeTrue()
        ->and($fields['personal_data.contacts']['editable'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-004 — merge restriction: a role config with visible:false hides the
// field for a member (visible=false, hidden=true).
// ---------------------------------------------------------------------------

it('AC-004: a role restricting users.personal_data.tax_code visible:false hides the field for a member', function () {
    $actor = actorWithFieldPermissionRole(
        ['view', 'update'],
        ['resource' => 'users', 'field' => 'personal_data.tax_code', 'visible' => false, 'editable' => true, 'required' => false],
    );
    $target = User::factory()->create();
    PersonalData::factory()->for($target, 'personable')->create();
    Sanctum::actingAs($actor);

    $field = $this->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.fields')['personal_data.tax_code'];

    expect($field['visible'])->toBeFalse()
        ->and($field['hidden'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-005 — no-escalation: the ceiling always wins over a permissive DB row.
// ---------------------------------------------------------------------------

it('AC-005: DB editable:true on personal_data.tax_code cannot override the ceiling when the actor may NOT update', function () {
    $actor = actorWithFieldPermissionRole(
        ['view'], // no users.update
        ['resource' => 'users', 'field' => 'personal_data.tax_code', 'visible' => true, 'editable' => true, 'required' => false],
    );
    $target = User::factory()->create();
    PersonalData::factory()->for($target, 'personable')->create();
    Sanctum::actingAs($actor);

    $field = $this->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.fields')['personal_data.tax_code'];

    expect($field['editable'])->toBeFalse()
        ->and($field['readonly'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-008 — bypass super-admin: a restrictive DB row never affects a
// super-admin actor's own ceiling.
// ---------------------------------------------------------------------------

it('AC-008: a super-admin actor is unaffected by a restrictive DB row on personal_data.tax_code', function () {
    $superRole = Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $superRole->fieldPermissions()->create([
        'resource' => 'users', 'field' => 'personal_data.tax_code', 'visible' => false, 'editable' => false, 'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);

    $target = User::factory()->create();
    PersonalData::factory()->for($target, 'personable')->create();
    Sanctum::actingAs($actor);

    $field = $this->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.fields')['personal_data.tax_code'];

    expect($field['visible'])->toBeTrue()
        ->and($field['editable'])->toBeTrue()
        ->and($field['hidden'])->toBeFalse();
});

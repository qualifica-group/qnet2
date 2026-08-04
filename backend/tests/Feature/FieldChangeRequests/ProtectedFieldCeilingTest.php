<?php

use App\Authorization\AuthorizationRegistry;
use App\Authorization\ProtectedFieldAwareAuthorization;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0078, microtask A4: ProtectedFieldAwareAuthorization narrows a
 * resource's ceiling for its config/field-change-requests.php protected
 * fields (currently only request-management.source_id).
 */
uses(RefreshDatabase::class);

if (! function_exists('requestManagementActorWithout')) {
    /**
     * @param  array<int, string>  $missingAbilities
     */
    function requestManagementActorWithout(array $missingAbilities): User
    {
        $abilities = ['viewAny', 'view', 'create', 'update', 'updateSource'];

        foreach ($abilities as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach (array_diff($abilities, $missingAbilities) as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-002 — readonly ceiling + change_requestable_fields for an actor without
// the protected field's permission
// ---------------------------------------------------------------------------

it('AC-002: source_id is visible+readonly and listed in change_requestable_fields without updateSource', function () {
    $actor = requestManagementActorWithout(['updateSource']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/request-management')
        ->assertOk()
        ->assertJsonPath('permissions.fields.source_id.readonly', true)
        ->assertJsonPath('permissions.fields.source_id.editable', false)
        ->assertJsonPath('permissions.fields.source_id.visible', true)
        ->assertJsonPath('permissions.change_requestable_fields', ['source_id']);
});

// ---------------------------------------------------------------------------
// AC-003 — full ceiling restored, field absent from change_requestable_fields
// ---------------------------------------------------------------------------

it('AC-003: source_id stays editable and is absent from change_requestable_fields with updateSource', function () {
    $actor = requestManagementActorWithout([]);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/request-management')->assertOk();

    $response->assertJsonPath('permissions.fields.source_id.editable', true);
    expect($response->json('permissions.change_requestable_fields'))->not->toContain('source_id');
});

// ---------------------------------------------------------------------------
// AC-004 — the privileged-role bypass precedes the restriction
// ---------------------------------------------------------------------------

it('AC-004: a super-admin sees source_id editable and an empty change_requestable_fields', function () {
    Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    Permission::findOrCreate('request-management.viewAny');
    $actor = User::factory()->create();
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/request-management')->assertOk();

    $response->assertJsonPath('permissions.fields.source_id.editable', true);
    expect($response->json('permissions.change_requestable_fields'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-005 — no regression on a resource with no protected fields
// ---------------------------------------------------------------------------

it('AC-005: a resource with no protected fields is never wrapped by ProtectedFieldAwareAuthorization', function () {
    $authorization = app(AuthorizationRegistry::class)->resolve('users');

    expect($authorization)->not->toBeInstanceOf(ProtectedFieldAwareAuthorization::class);
});

it('AC-005: GET /api/meta/users still emits an (empty) change_requestable_fields array', function () {
    foreach (['viewAny', 'view'] as $ability) {
        Permission::findOrCreate("users.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['users.viewAny', 'users.view']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/users')->assertOk();

    expect($response->json('permissions.change_requestable_fields'))->toBe([]);

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

// ---------------------------------------------------------------------------
// AC-006 — a mandatory protected field becomes readonly, never hidden/optional
// ---------------------------------------------------------------------------

it('AC-006: a mandatory protected field stays visible and required when restricted to readonly', function () {
    $actor = requestManagementActorWithout(['updateSource']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/request-management')
        ->assertOk()
        ->assertJsonPath('permissions.fields.source_id.visible', true)
        ->assertJsonPath('permissions.fields.source_id.readonly', true)
        ->assertJsonPath('permissions.fields.source_id.required', true)
        ->assertJsonPath('permissions.fields.source_id.hidden', false);
});

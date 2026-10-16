<?php

use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

if (! function_exists('actorWithImpersonatePermission')) {
    function actorWithImpersonatePermission(): User
    {
        Permission::findOrCreate('users.impersonate');

        $actor = User::factory()->create();
        $actor->givePermissionTo('users.impersonate');

        return $actor;
    }
}

if (! function_exists('superAdminActor')) {
    function superAdminActor(): User
    {
        Role::findOrCreate(RoleAssignmentGuard::PRIVILEGED_ROLE);

        $actor = User::factory()->create();
        $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);

        return $actor;
    }
}

/**
 * A REAL, database-backed personal access token (never Sanctum::actingAs()'s
 * mock): ImpersonationService reads `impersonated_by` off the CURRENT token,
 * and a Mockery-mocked token answers every unstubbed attribute with `false`
 * (not `null`), which would trip the D-2 nesting guard for the wrong reason.
 */
if (! function_exists('tokenFor')) {
    function tokenFor(User $user): string
    {
        return $user->createToken('api')->plainTextToken;
    }
}

/**
 * Switches the simulated caller to $token for the NEXT request.
 * Auth::forgetGuards() is required on every switch: Sanctum's RequestGuard
 * caches the first user it resolves for the guard's lifetime, so a bare
 * withToken() with a DIFFERENT token would silently keep authenticating as
 * whoever was resolved first — several tests here impersonate, then act
 * again as the resulting (different) token.
 */
if (! function_exists('asToken')) {
    function asToken(TestCase $test, string $token): TestCase
    {
        Auth::forgetGuards();

        return $test->withToken($token);
    }
}

/**
 * A minimal valid create-user payload (mirrors UserCrudTest's individualProfile()
 * under a distinct name — guarded redeclare safety across test files).
 *
 * @return array<string, mixed>
 */
if (! function_exists('impersonationCreateUserPayload')) {
    function impersonationCreateUserPayload(string $email): array
    {
        return [
            'email' => $email,
            'locale' => 'it',
            'password' => 'Str0ng-P4ssw0rd!',
            'password_confirmation' => 'Str0ng-P4ssw0rd!',
            'personal_data' => ['type' => 'individual', 'first_name' => 'New', 'last_name' => 'Person'],
            'employment' => ['covers_all_product_categories' => true],
        ];
    }
}

if (! function_exists('impersonationRowsPayload')) {
    function impersonationRowsPayload(): array
    {
        return ['startRow' => 0, 'endRow' => 25];
    }
}

// ---------------------------------------------------------------------------
// start — POST /api/users/{user}/impersonate
// ---------------------------------------------------------------------------

it('AC-001: starts a session; /auth/me under the new token returns the target', function () {
    $actor = actorWithImpersonatePermission();
    $target = User::factory()->create();

    $response = asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.id', $target->id);

    asToken($this, $response->json('data.token'))
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $target->id);
});

it('AC-002: /auth/me/abilities under the impersonation token reflects the target, not the actor', function () {
    Permission::findOrCreate('leads.view');
    $actor = actorWithImpersonatePermission();
    $actor->givePermissionTo('leads.view');
    $target = User::factory()->create(); // no leads.view

    $token = asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk()->json('data.token');

    $abilities = asToken($this, $token)
        ->getJson('/api/auth/me/abilities')
        ->assertOk()
        ->json('data');

    expect($abilities['permissions']['leads.view'])->toBeFalse();
});

it('AC-003: a write authorized to the target succeeds and is attributed to the target', function () {
    Permission::findOrCreate('users.create');
    $actor = superAdminActor();
    $target = User::factory()->create();
    $target->givePermissionTo('users.create');

    $token = asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk()->json('data.token');

    asToken($this, $token)
        ->postJson('/api/users', impersonationCreateUserPayload('attributed@example.com'))
        ->assertCreated();

    $created = User::where('email', 'attributed@example.com')->sole();

    $activity = Activity::query()
        ->where('subject_type', $created->getMorphClass())
        ->where('subject_id', $created->id)
        ->where('event', 'created')
        ->sole();

    expect($activity->causer_id)->toBe($target->id);
});

it('AC-004: a write NOT permitted to the target is 403 even though the actor could do it', function () {
    Permission::findOrCreate('users.create');
    $actor = superAdminActor();
    $target = User::factory()->create(); // no users.create

    $token = asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk()->json('data.token');

    asToken($this, $token)
        ->postJson('/api/users', impersonationCreateUserPayload('blocked@example.com'))
        ->assertForbidden();
});

it('AC-005: an actor without users.impersonate is 403', function () {
    Permission::findOrCreate('users.impersonate');
    $actor = User::factory()->create();
    $target = User::factory()->create();

    asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertForbidden();
});

it('AC-006: self-impersonation is 422', function () {
    $actor = actorWithImpersonatePermission();

    asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$actor->id}/impersonate")
        ->assertStatus(422)
        ->assertJsonValidationErrors('user');
});

it('AC-007: an inactive target is 422', function () {
    $actor = actorWithImpersonatePermission();
    $target = User::factory()->inactive()->create();

    asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertStatus(422)
        ->assertJsonValidationErrors('user');
});

it('AC-008: a non-super-admin actor cannot impersonate a super-admin (403)', function () {
    $actor = actorWithImpersonatePermission();
    $target = superAdminActor();

    asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertForbidden();
});

it('AC-009: starting a session while already impersonating is 422 (no nesting)', function () {
    $actor = superAdminActor();
    // Also super-admin: its own subsequent impersonate call is shortcut by
    // Gate::before, isolating the D-2 nesting check in the Service (which
    // does not depend on the Policy running at all).
    $target = superAdminActor();
    $another = User::factory()->create();

    $token = asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk()->json('data.token');

    asToken($this, $token)
        ->postJson("/api/users/{$another->id}/impersonate")
        ->assertStatus(422)
        ->assertJsonValidationErrors('user');
});

// ---------------------------------------------------------------------------
// GET /api/auth/impersonation — banner state (D-5)
// ---------------------------------------------------------------------------

it('AC-010: reports impersonator=null normally, and the original actor while impersonating', function () {
    $actor = actorWithImpersonatePermission();
    $target = User::factory()->create();

    asToken($this, tokenFor($actor))
        ->getJson('/api/auth/impersonation')
        ->assertOk()
        ->assertJsonPath('data.impersonator', null);

    $token = asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk()->json('data.token');

    asToken($this, $token)
        ->getJson('/api/auth/impersonation')
        ->assertOk()
        ->assertJsonPath('data.impersonator.id', $actor->id)
        ->assertJsonPath('data.impersonator.email', $actor->email);
});

// ---------------------------------------------------------------------------
// stop — POST /api/auth/stop-impersonation
// ---------------------------------------------------------------------------

it('AC-011: stop restores the original actor and revokes the impersonation token', function () {
    $actor = actorWithImpersonatePermission();
    $target = User::factory()->create();

    $impersonationToken = asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk()->json('data.token');

    $response = asToken($this, $impersonationToken)
        ->postJson('/api/auth/stop-impersonation')
        ->assertOk()
        ->assertJsonPath('data.user.id', $actor->id);

    // The impersonation token is gone: reusing it is unauthorized.
    asToken($this, $impersonationToken)->getJson('/api/auth/me')->assertUnauthorized();

    // The new token restores the original actor.
    asToken($this, $response->json('data.token'))
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $actor->id);
});

it('AC-012: stop with a normal (non-impersonation) token is 403', function () {
    $actor = User::factory()->create();

    asToken($this, tokenFor($actor))
        ->postJson('/api/auth/stop-impersonation')
        ->assertForbidden();
});

it('stop is 403 and revokes the impersonation token when the original actor was deactivated meanwhile', function () {
    $actor = actorWithImpersonatePermission();
    $target = User::factory()->create();

    $impersonationToken = asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk()->json('data.token');

    // The original actor is deactivated WHILE the target is impersonating.
    $actor->update(['is_active' => false]);

    asToken($this, $impersonationToken)
        ->postJson('/api/auth/stop-impersonation')
        ->assertForbidden();

    // No token was re-issued for the (now inactive) original actor, and the
    // impersonation token itself was revoked either way.
    asToken($this, $impersonationToken)->getJson('/api/auth/me')->assertUnauthorized();
});

it('edge case: the original actor being deleted mid-session leaves stop() at 403, but a plain logout still works', function () {
    $actor = actorWithImpersonatePermission();
    $target = User::factory()->create();

    $impersonationToken = asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk()->json('data.token');

    // Deleting the original actor nulls `impersonated_by` (nullOnDelete):
    // stop() then reads it as "not an impersonation session" (same 403 as
    // AC-012), never a 404 — the token row itself is untouched.
    $actor->delete();

    asToken($this, $impersonationToken)
        ->postJson('/api/auth/stop-impersonation')
        ->assertForbidden();

    // The impersonated user is not stuck: a plain logout still revokes the
    // (still valid) impersonation token regardless of `impersonated_by`.
    asToken($this, $impersonationToken)
        ->postJson('/api/auth/logout')
        ->assertOk();

    asToken($this, $impersonationToken)->getJson('/api/auth/me')->assertUnauthorized();
});

it('D-4: the actor original token stays valid after starting a session', function () {
    $actor = actorWithImpersonatePermission();
    $originalToken = tokenFor($actor);
    $target = User::factory()->create();

    asToken($this, $originalToken)
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk();

    asToken($this, $originalToken)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $actor->id);
});

// ---------------------------------------------------------------------------
// AC-013 — audit (D-6)
// ---------------------------------------------------------------------------

it('AC-013: start and stop each write an activity entry with causer=actor and subject=target', function () {
    $actor = actorWithImpersonatePermission();
    $target = User::factory()->create();

    $token = asToken($this, tokenFor($actor))
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk()->json('data.token');

    $started = Activity::query()
        ->where('description', 'impersonation.started')
        ->where('subject_type', $target->getMorphClass())
        ->where('subject_id', $target->id)
        ->sole();

    expect($started->causer_id)->toBe($actor->id);

    asToken($this, $token)->postJson('/api/auth/stop-impersonation')->assertOk();

    $stopped = Activity::query()
        ->where('description', 'impersonation.stopped')
        ->where('subject_type', $target->getMorphClass())
        ->where('subject_id', $target->id)
        ->sole();

    expect($stopped->causer_id)->toBe($actor->id);
});

// ---------------------------------------------------------------------------
// AC-014 — table row action
// ---------------------------------------------------------------------------

it('AC-014: the users table row exposes `impersonate` only when the Policy allows it', function () {
    Permission::findOrCreate('users.viewAny');
    Permission::findOrCreate('users.impersonate');
    $withPermission = User::factory()->create();
    $withPermission->givePermissionTo(['users.viewAny', 'users.impersonate']);
    $withoutPermission = User::factory()->create();
    $withoutPermission->givePermissionTo('users.viewAny');
    $target = User::factory()->create();

    $rows = collect(asToken($this, tokenFor($withPermission))
        ->postJson('/api/tables/users/rows', impersonationRowsPayload())
        ->assertOk()->json('items'))->keyBy('id');
    expect($rows[$target->id]['actions'])->toContain('impersonate');

    $rows = collect(asToken($this, tokenFor($withoutPermission))
        ->postJson('/api/tables/users/rows', impersonationRowsPayload())
        ->assertOk()->json('items'))->keyBy('id');
    expect($rows[$target->id]['actions'])->not->toContain('impersonate');
});

// ---------------------------------------------------------------------------
// AC-015 — permission catalogue
// ---------------------------------------------------------------------------

it('AC-015: php artisan permissions:sync creates users.impersonate', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'users.impersonate')->exists())->toBeTrue();
});

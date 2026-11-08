<?php

use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Welcome tour (spec 0368): the state is computed from users.onboarding_seen_version
 * against config('auth.onboarding_version') and registered by PUT /auth/me/onboarding.
 */
it('AC-001: GET /auth/me reports required, outdated and completed', function () {
    config(['auth.onboarding_version' => 2]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.onboarding_status', 'required');

    $user->forceFill(['onboarding_seen_version' => 1])->save();
    $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.onboarding_status', 'outdated');

    $user->forceFill(['onboarding_seen_version' => 2])->save();
    $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.onboarding_status', 'completed');
});

it('AC-002: completed from a required state stores the current version', function () {
    config(['auth.onboarding_version' => 3]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/me/onboarding', ['outcome' => 'completed'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.onboarding_status', 'completed');

    $user->refresh();
    expect($user->onboarding_seen_version)->toBe(3)
        ->and($user->onboarding_seen_at)->not->toBeNull();
});

it('AC-003: skipped from an outdated state completes it, and repeating is idempotent', function () {
    config(['auth.onboarding_version' => 2]);
    $user = User::factory()->create(['name' => 'Tour User']);
    $user->forceFill(['onboarding_seen_version' => 1])->save();
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/me/onboarding', ['outcome' => 'skipped'])
        ->assertOk()
        ->assertJsonPath('data.onboarding_status', 'completed');
    $this->putJson('/api/auth/me/onboarding', ['outcome' => 'skipped'])->assertOk();

    expect($user->refresh()->onboarding_seen_version)->toBe(2);
});

it('AC-004: skipped from a required state is 422 and writes nothing', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/me/onboarding', ['outcome' => 'skipped'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('outcome');

    $user->refresh();
    expect($user->onboarding_seen_version)->toBeNull()
        ->and($user->onboarding_seen_at)->toBeNull();
});

it('AC-005: an impersonation token is refused with 403 for both outcomes and writes nothing', function (string $outcome) {
    Role::findOrCreate(RoleAssignmentGuard::PRIVILEGED_ROLE);
    $actor = User::factory()->create();
    $actor->assignRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
    $target = User::factory()->create();

    $token = $actor->createToken('api')->plainTextToken;
    $impersonation = $this->withToken($token)
        ->postJson("/api/users/{$target->id}/impersonate")
        ->assertOk()
        ->json('data.token');

    Auth::forgetGuards();
    $this->withToken($impersonation)
        ->putJson('/api/auth/me/onboarding', ['outcome' => $outcome])
        ->assertForbidden()
        ->assertExactJson([
            'success' => false,
            'message' => __('auth.onboarding_impersonation'),
        ]);

    $target->refresh();
    expect($target->onboarding_seen_version)->toBeNull()
        ->and($target->onboarding_seen_at)->toBeNull();
})->with(['completed', 'skipped']);

it('AC-006: a missing or invalid outcome is 422, unauthenticated is 401', function (array $payload) {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/auth/me/onboarding', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors('outcome');
})->with([[[]], [['outcome' => 'done']], [['outcome' => ['completed']]]]);

it('AC-006: unauthenticated is 401', function () {
    $this->putJson('/api/auth/me/onboarding', ['outcome' => 'completed'])->assertUnauthorized();
});

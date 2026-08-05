<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Per-user module open mode preference (spec 0042, AC-001..AC-009). Persisted
 * as a single `module_open_preferences` JSON column on `users`, exposed and
 * saved through the existing GET/PATCH /api/auth/me — no new endpoint.
 */

// ---------------------------------------------------------------------------
// GET /auth/me — default when unset (AC-002)
// ---------------------------------------------------------------------------

it('GET /auth/me defaults module_open_preferences to page with no overrides when unset', function () {
    $user = User::factory()->create(['module_open_preferences' => null]);
    Sanctum::actingAs($user);

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.module_open_preferences', ['mode' => 'page', 'overrides' => []]);
});

// ---------------------------------------------------------------------------
// PATCH /auth/me — global mode persists and round-trips (AC-003)
// ---------------------------------------------------------------------------

it('PATCH /auth/me persists a global mode and a following GET reflects it', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'module_open_preferences' => ['mode' => 'modal'],
    ])->assertOk()
        ->assertJsonPath('data.module_open_preferences', ['mode' => 'modal', 'overrides' => []]);

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.module_open_preferences', ['mode' => 'modal', 'overrides' => []]);

    expect($user->fresh()->module_open_preferences)->toBe(['mode' => 'modal', 'overrides' => []]);
});

// ---------------------------------------------------------------------------
// PATCH /auth/me — custom mode with overrides persists exactly (AC-004)
// ---------------------------------------------------------------------------

it('PATCH /auth/me persists custom mode with the exact overrides submitted', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'module_open_preferences' => [
            'mode' => 'custom',
            'overrides' => ['projects' => 'page', 'campaigns' => 'modal'],
        ],
    ])->assertOk()
        ->assertJsonPath('data.module_open_preferences.mode', 'custom')
        ->assertJsonPath('data.module_open_preferences.overrides.projects', 'page')
        ->assertJsonPath('data.module_open_preferences.overrides.campaigns', 'modal');

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.module_open_preferences.overrides.projects', 'page')
        ->assertJsonPath('data.module_open_preferences.overrides.campaigns', 'modal');

    expect($user->fresh()->module_open_preferences)->toBe([
        'mode' => 'custom',
        'overrides' => ['projects' => 'page', 'campaigns' => 'modal'],
    ]);
});

// ---------------------------------------------------------------------------
// error paths (AC-005, AC-006, AC-007)
// ---------------------------------------------------------------------------

it('rejects a mode outside modal/page/custom', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'module_open_preferences' => ['mode' => 'sheet'],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('module_open_preferences.mode');
});

it('rejects an override value outside modal/page', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'module_open_preferences' => [
            'mode' => 'custom',
            'overrides' => ['projects' => 'sheet'],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('module_open_preferences.overrides.projects');
});

it('rejects an unknown override key', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'module_open_preferences' => [
            'mode' => 'custom',
            'overrides' => ['foo' => 'modal'],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('module_open_preferences.overrides.foo');
});

it('rejects the non-CRUD import-runs domain as an override key', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'module_open_preferences' => [
            'mode' => 'custom',
            'overrides' => ['import-runs' => 'modal'],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('module_open_preferences.overrides.import-runs');
});

// ---------------------------------------------------------------------------
// self-scope / mass-assignment guard (AC-008)
// ---------------------------------------------------------------------------

it('PATCH /auth/me only ever touches the authenticated user, never another one', function () {
    $other = User::factory()->create(['module_open_preferences' => null]);
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'module_open_preferences' => ['mode' => 'modal'],
    ])->assertOk();

    expect($other->fresh()->module_open_preferences)->toBeNull();
});

it('module_open_preferences is not mass-assignable outside the updateProfile flow', function () {
    $user = User::factory()->create(['module_open_preferences' => null]);

    $user->update(['module_open_preferences' => ['mode' => 'modal', 'overrides' => []]]);

    expect($user->fresh()->module_open_preferences)->toBeNull();
});

// ---------------------------------------------------------------------------
// no regression on the rest of updateProfile (AC-009)
// ---------------------------------------------------------------------------

it('updating module_open_preferences alongside locale leaves both correct, no regression', function () {
    $user = User::factory()->create(['locale' => 'en']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'locale' => 'it',
        'module_open_preferences' => ['mode' => 'page'],
    ])->assertOk()
        ->assertJsonPath('data.locale', 'it')
        ->assertJsonPath('data.module_open_preferences.mode', 'page');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'it']);
});

it('omitting module_open_preferences from the payload leaves the stored preference untouched', function () {
    $user = User::factory()->create(['module_open_preferences' => ['mode' => 'modal', 'overrides' => []]]);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'locale' => 'it',
    ])->assertOk()
        ->assertJsonPath('data.module_open_preferences', ['mode' => 'modal', 'overrides' => []]);

    expect($user->fresh()->module_open_preferences)->toBe(['mode' => 'modal', 'overrides' => []]);
});

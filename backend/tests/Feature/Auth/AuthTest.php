<?php

use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('logs in with valid credentials and returns a token', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'token',
                'token_type',
                'user' => ['id', 'name', 'email', 'roles', 'created_at'],
            ],
        ])
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', $user->email)
        ->assertJsonPath('data.token_type', 'Bearer');

    expect($user->tokens()->count())->toBe(1);
});

it('rejects login with invalid credentials', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('email');
});

it('denies login to an inactive account despite valid credentials', function () {
    $user = User::factory()->inactive()->create([
        'password' => bcrypt('password'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('email')
        ->assertJsonPath('errors.email.0', __('auth.inactive'));

    expect($user->tokens()->count())->toBe(0);
});

it('validates required login fields', function () {
    $this->postJson('/api/auth/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

it('returns the authenticated user on /me with a UserResource', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonStructure(['success', 'message', 'data' => ['id', 'name', 'email', 'roles', 'created_at']]);
});

/**
 * The client caches this payload as the current user, and the
 * request-management create form defaults the Sede operativa from
 * `employment.operational_site_id` (user directive 2026-08-04) — so the key
 * must be there, not only on the Users module's own endpoints.
 */
it('exposes the authenticated user employment Sede on /me', function () {
    $user = User::factory()->create();
    $site = OperationalSite::factory()->create();
    EmploymentProfile::factory()->create(['user_id' => $user->id, 'operational_site_id' => $site->id]);

    Sanctum::actingAs($user);

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.employment.operational_site_id', $site->id);
});

it('blocks /me without authentication', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
});

it('logs out and revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Logged out successfully.');

    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('refreshes the token by rotating it', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/auth/refresh')
        ->assertOk()
        ->assertJsonStructure(['success', 'message', 'data' => ['token', 'token_type']]);

    // Vecchio token revocato, esattamente un token attivo (quello nuovo).
    expect($response->json('data.token'))->not->toBe($token);
    expect($user->fresh()->tokens()->count())->toBe(1);
});

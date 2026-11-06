<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('AC-001: the gate default is on only in production', function (string $environment, bool $expected) {
    expect(configDefaultUnderEnvironment('auth', 'first_access_gate', 'AUTH_FIRST_ACCESS_GATE', $environment))->toBe($expected);
})->with([
    'production' => ['production', true],
    'local' => ['local', false],
    'staging' => ['staging', false],
    'testing' => ['testing', false],
]);

it('AC-002: with the gate off a flagged user reaches the API and is not asked to change password', function () {
    config(['auth.first_access_gate' => false]);
    $user = firstAccessPendingUser();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->getJson('/api/navigation')->assertOk();

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')
        ->assertOk()->assertJsonPath('data.must_set_password', false);

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'test'])
        ->assertOk()->assertJsonPath('data.user.must_set_password', false);

    expect($user->fresh()->must_set_password)->toBeTrue();
});

it('AC-003: with the gate on the same user is blocked and asked to change password', function () {
    config(['auth.first_access_gate' => true]);
    $user = firstAccessPendingUser();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->getJson('/api/navigation')
        ->assertForbidden()->assertJsonPath('authorization.reason', 'password_change_required');

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')
        ->assertOk()->assertJsonPath('data.must_set_password', true);

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password', 'device_name' => 'test'])
        ->assertOk()->assertJsonPath('data.user.must_set_password', true);
});

it('AC-004: the Users module still shows the raw flag with the gate off', function () {
    config(['auth.first_access_gate' => false]);
    $target = firstAccessPendingUser();

    $this->withToken(userWithUserAbilities(['view'])->createToken('api')->plainTextToken)
        ->getJson("/api/users/{$target->id}")
        ->assertOk()->assertJsonPath('data.must_set_password', true);
});

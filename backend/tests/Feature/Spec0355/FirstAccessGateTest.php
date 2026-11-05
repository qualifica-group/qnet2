<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

const GATE_PASSWORD_PAYLOAD = ['password' => 'Brand-New-Pass1!', 'password_confirmation' => 'Brand-New-Pass1!'];

it('AC-003: a pending user is blocked everywhere but on the allow-listed endpoints', function () {
    $token = firstAccessPendingUser()->createToken('api')->plainTextToken;

    foreach ([
        fn () => $this->withToken($token)->getJson('/api/navigation'),
        fn () => $this->withToken($token)->patchJson('/api/auth/me', ['locale' => 'en']),
        fn () => $this->withToken($token)->postJson('/api/broadcasting/auth'),
    ] as $blocked) {
        Auth::forgetGuards();
        $blocked()->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('authorization.reason', 'password_change_required')
            ->assertJsonPath('authorization.permission', null);
    }

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')->assertOk();
    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me/abilities')->assertOk();
    Auth::forgetGuards();
    $this->withToken($token)->putJson('/api/auth/me/first-password', GATE_PASSWORD_PAYLOAD)->assertOk();
});

it('AC-003: logout stays available while the first password is pending', function () {
    $token = firstAccessPendingUser()->createToken('api')->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
});

it('AC-003: requests without a token or with an invalid one still end in the route 401', function () {
    $this->getJson('/api/navigation')->assertUnauthorized();
    $this->withToken('invalid-token')->getJson('/api/navigation')->assertUnauthorized();
});

it('AC-004: after choosing the first password the same token reaches the rest of the API', function () {
    $token = firstAccessPendingUser()->createToken('api')->plainTextToken;

    $this->withToken($token)->getJson('/api/navigation')->assertForbidden();

    Auth::forgetGuards();
    $this->withToken($token)->putJson('/api/auth/me/first-password', GATE_PASSWORD_PAYLOAD)->assertOk();

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/navigation')->assertOk();
});

it('AC-005: impersonation tokens and users without the flag are never blocked', function () {
    $admin = User::factory()->create();
    $target = firstAccessPendingUser();
    $issued = $target->createToken('impersonation');
    $issued->accessToken->forceFill(['impersonated_by' => $admin->id])->save();

    $this->withToken($issued->plainTextToken)->getJson('/api/navigation')->assertOk();

    Auth::forgetGuards();
    $plain = User::factory()->create();
    $this->withToken($plain->createToken('api')->plainTextToken)->getJson('/api/navigation')->assertOk();
});

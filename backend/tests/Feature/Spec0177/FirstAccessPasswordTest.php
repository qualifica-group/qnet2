<?php

use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function firstAccessSetPayload(User $user, string $token, array $overrides = []): array
{
    return array_merge([
        'token' => $token,
        'email' => $user->email,
        'password' => 'Brand-New-Pass1!',
        'password_confirmation' => 'Brand-New-Pass1!',
    ], $overrides);
}

// REQUIREMENT CHANGED (spec 0355): AC-004..AC-006 (invite token flow, `set-password`) are
// removed with the endpoint; the first access is now enforced server-side (AC-007).

it('AC-007: a pending user is blocked everywhere but can choose a password via first-password', function () {
    // REQUIREMENT CHANGED (spec 0359): the gate is on only in production.
    config(['auth.first_access_gate' => true]);
    $user = firstAccessPendingUser();
    $token = $user->createToken('api')->plainTextToken;
    $other = $user->createToken('other');

    $this->withToken($token)->getJson('/api/navigation')
        ->assertForbidden()
        ->assertJsonPath('authorization.reason', 'password_change_required');

    Auth::forgetGuards();
    $this->withToken($token)->putJson('/api/auth/me/first-password', [
        'password' => 'Brand-New-Pass1!',
        'password_confirmation' => 'Brand-New-Pass1!',
    ])->assertOk()->assertJsonPath('message', __('auth.first_password_updated'));

    $user->refresh();
    expect($user->must_set_password)->toBeFalse()
        ->and(Hash::check('Brand-New-Pass1!', $user->password))->toBeTrue()
        ->and($user->tokens()->whereKey($other->accessToken->id)->exists())->toBeFalse()
        ->and($user->tokens()->count())->toBe(1);

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')->assertOk();
    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'Brand-New-Pass1!', 'device_name' => 'test'])
        ->assertOk();
});

it('AC-008: first-password is 403 without the flag or under impersonation, 422 on bad input', function () {
    $payload = ['password' => 'Brand-New-Pass1!', 'password_confirmation' => 'Brand-New-Pass1!'];

    $plain = User::factory()->create();
    $this->withToken($plain->createToken('api')->plainTextToken)
        ->putJson('/api/auth/me/first-password', $payload)
        ->assertForbidden()
        ->assertJsonPath('success', false);

    Auth::forgetGuards();
    $admin = User::factory()->create();
    $target = firstAccessPendingUser();
    $issued = $target->createToken('impersonation');
    $issued->accessToken->forceFill(['impersonated_by' => $admin->id])->save();
    $this->withToken($issued->plainTextToken)
        ->putJson('/api/auth/me/first-password', $payload)
        ->assertForbidden()
        ->assertJsonPath('success', false);
    expect($target->fresh()->must_set_password)->toBeTrue();

    Auth::forgetGuards();
    $token = $target->createToken('api')->plainTextToken;
    $this->withToken($token)->putJson('/api/auth/me/first-password', [
        'password' => 'Brand-New-Pass1!',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');

    Auth::forgetGuards();
    $this->withToken($token)->putJson('/api/auth/me/first-password', [
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');

    expect($target->fresh()->must_set_password)->toBeTrue();
});

it('AC-009: changing the password clears the flag; reusing the current one is a 422', function () {
    $user = firstAccessPendingUser();
    Sanctum::actingAs($user);

    $this->putJson('/api/auth/me/password', [
        'current_password' => 'password',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect($user->fresh()->must_set_password)->toBeTrue();

    $this->putJson('/api/auth/me/password', [
        'current_password' => 'password',
        'password' => 'Brand-New-Pass1!',
        'password_confirmation' => 'Brand-New-Pass1!',
    ])->assertOk();
    expect($user->fresh()->must_set_password)->toBeFalse();
});

it('AC-010: the forgot-password reset clears the flag of a pending user', function () {
    $user = firstAccessPendingUser();
    $token = Password::createToken($user);

    $this->postJson('/api/auth/reset-password', firstAccessSetPayload($user, $token))->assertOk();

    expect($user->fresh()->must_set_password)->toBeFalse();
});

// REQUIREMENT CHANGED (spec 0355): AC-011 (resend only for pending users, invite token) is
// replaced by tests/Feature/Spec0355/WelcomeResendTest.php.

it('AC-012: an admin password reset on another user flags them, on self it does not', function () {
    Notification::fake();
    $admin = userWithUserAbilities(['update']);
    $target = User::factory()->create();
    Sanctum::actingAs($admin);
    $payload = ['password' => 'Str0ng-P4ssw0rd!', 'password_confirmation' => 'Str0ng-P4ssw0rd!'];

    $this->patchJson("/api/users/{$target->id}", $payload)
        ->assertOk()->assertJsonPath('data.must_set_password', true);
    expect($target->fresh()->must_set_password)->toBeTrue();

    $this->patchJson("/api/users/{$admin->id}", $payload)
        ->assertOk()->assertJsonPath('data.must_set_password', false);

    Notification::assertNothingSent();
    expect(Notification::sent($target, WelcomeUserNotification::class))->toHaveCount(0);
});

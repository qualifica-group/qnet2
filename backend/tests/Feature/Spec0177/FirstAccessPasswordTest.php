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

it('AC-004: a valid invite token sets the password, clears the flag, is single use and revokes sessions', function () {
    $user = firstAccessPendingUser();
    $user->createToken('old');
    $token = firstAccessInviteToken($user);

    $this->postJson('/api/auth/set-password', firstAccessSetPayload($user, $token))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('passwords.set'));

    $user->refresh();
    expect($user->must_set_password)->toBeFalse()
        ->and(Hash::check('Brand-New-Pass1!', $user->password))->toBeTrue()
        ->and($user->tokens()->count())->toBe(0);

    $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'Brand-New-Pass1!', 'device_name' => 'test'])
        ->assertOk();

    $this->postJson('/api/auth/set-password', firstAccessSetPayload($user, $token))
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('AC-005: wrong token, wrong email or an expired token is a 422 on email and keeps the password', function () {
    $user = firstAccessPendingUser();
    $oldHash = $user->password;
    $token = firstAccessInviteToken($user);
    $other = User::factory()->create();

    $this->postJson('/api/auth/set-password', firstAccessSetPayload($user, 'wrong-token'))
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/api/auth/set-password', firstAccessSetPayload($other, $token))
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->travel(73)->hours();
    $this->postJson('/api/auth/set-password', firstAccessSetPayload($user, $token))
        ->assertUnprocessable()->assertJsonValidationErrors('email');

    expect($user->fresh()->password)->toBe($oldHash)
        ->and($user->fresh()->must_set_password)->toBeTrue();
});

it('AC-005: a token younger than 72h is still accepted', function () {
    $user = firstAccessPendingUser();
    $token = firstAccessInviteToken($user);

    $this->travel(71)->hours();
    $this->postJson('/api/auth/set-password', firstAccessSetPayload($user, $token))->assertOk();
});

it('AC-006: reset and invite tokens are not interchangeable', function () {
    $user = firstAccessPendingUser();
    $resetToken = Password::createToken($user);
    $inviteToken = firstAccessInviteToken($user);

    $this->postJson('/api/auth/set-password', firstAccessSetPayload($user, $resetToken))
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/api/auth/reset-password', firstAccessSetPayload($user, $inviteToken))
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('AC-007: a pending user is blocked from the protected API but keeps the auth self-service routes', function () {
    $user = firstAccessPendingUser();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->getJson('/api/navigation')
        ->assertForbidden()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', __('auth.must_set_password'));

    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me')->assertOk();
    Auth::forgetGuards();
    $this->withToken($token)->getJson('/api/auth/me/abilities')->assertOk();
    Auth::forgetGuards();
    $this->withToken($token)->putJson('/api/auth/me/password', [
        'current_password' => 'password',
        'password' => 'Brand-New-Pass1!',
        'password_confirmation' => 'Brand-New-Pass1!',
    ])->assertOk();
    Auth::forgetGuards();
    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
});

it('AC-008: an impersonation session of a pending user is not blocked', function () {
    $admin = User::factory()->create();
    $target = firstAccessPendingUser();

    $issued = $target->createToken('impersonation');
    $issued->accessToken->forceFill(['impersonated_by' => $admin->id])->save();

    $this->withToken($issued->plainTextToken)->getJson('/api/navigation')->assertOk();
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

it('AC-011: resend-welcome sends a new invite and invalidates the previous token', function () {
    Notification::fake();
    Sanctum::actingAs(userWithUserAbilities(['update']));
    $target = firstAccessPendingUser();
    $oldToken = firstAccessInviteToken($target);

    $this->postJson("/api/users/{$target->id}/resend-welcome")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', __('auth.welcome_resent'))
        ->assertJsonPath('data', null);

    $notification = firstAccessSentWelcome($target);
    expect($notification->isInvite())->toBeTrue();
    expect(firstAccessWelcomeUrl($notification, $target))->toContain('/set-password?token=');

    $this->postJson('/api/auth/set-password', firstAccessSetPayload($target, $oldToken))
        ->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('AC-011: resend-welcome is 422 for an already activated user and 403 without permission', function () {
    Notification::fake();
    $target = User::factory()->create();

    Sanctum::actingAs(userWithUserAbilities(['update']));
    $this->postJson("/api/users/{$target->id}/resend-welcome")
        ->assertUnprocessable()->assertJsonValidationErrors('user');

    Sanctum::actingAs(userWithUserAbilities(['view']));
    $this->postJson('/api/users/'.firstAccessPendingUser()->id.'/resend-welcome')->assertForbidden();

    Sanctum::actingAs(userWithUserAbilities(['update']));
    $this->postJson('/api/users/999999/resend-welcome')->assertNotFound();

    Notification::assertNothingSent();
});

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

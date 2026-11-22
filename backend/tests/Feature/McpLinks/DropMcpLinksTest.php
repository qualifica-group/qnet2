<?php

use App\Enums\McpLinkReason;
use App\Models\EmploymentProfile;
use App\Models\McpLink;
use App\Models\User;
use App\Services\McpLinks\McpLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['mcp-link.release_commit' => 'abc1234']));

/**
 * A user with an open link, one token issued by an exchange, and the key to call the module.
 *
 * @return array{0: User, 1: McpLink, 2: string, 3: string} user, link, key, credential
 */
function linkedUserWithToken(): array
{
    [$key, $credential, $user, $link] = openLinkFor();
    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertOk();

    expect($link->tokens()->count())->toBe(1);

    return [$user, $link, $key, $credential];
}

function expectLinkDropped(McpLink $link, User $user, McpLinkReason $reason, string $key, string $credential): void
{
    expect($link->fresh()->revoked_at)->not->toBeNull()
        ->and($link->fresh()->revoked_reason)->toBe($reason)
        ->and($user->tokens()->where('name', 'like', 'mcp-link-%')->count())->toBe(0);

    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertUnauthorized();
}

it('drops the links when the user changes the password', function () {
    [$user, $link, $key, $credential] = linkedUserWithToken();
    $current = $user->createToken('app');

    callWithToken('PUT', '/api/auth/me/password', $current->plainTextToken, [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expectLinkDropped($link, $user, McpLinkReason::PasswordChanged, $key, $credential);
});

it('drops the links when the password is changed with a token issued for the link itself', function () {
    [$user, $link, $key, $credential] = linkedUserWithToken();
    $linkToken = $user->tokens()->where('name', 'like', 'mcp-link-%')->firstOrFail();
    $linkToken->forceFill(['token' => hash('sha256', 'plain')])->save();

    callWithToken('PUT', '/api/auth/me/password', $linkToken->id.'|plain', [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expectLinkDropped($link, $user, McpLinkReason::PasswordChanged, $key, $credential);
});

it('drops the links when an administrator sets the password', function () {
    // Created first: a call with the key switches the default guard, and permissions are guard-scoped.
    $admin = userWithUserAbilities(['update']);
    [$user, $link, $key, $credential] = linkedUserWithToken();
    Sanctum::actingAs($admin);

    $this->putJson("/api/users/{$user->id}", ['password' => 'Admin-set-pass-123', 'password_confirmation' => 'Admin-set-pass-123'])->assertOk();

    expectLinkDropped($link, $user, McpLinkReason::PasswordChanged, $key, $credential);
});

it('drops the links when the password is reset', function () {
    [$user, $link, $key, $credential] = linkedUserWithToken();

    $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => Password::createToken($user),
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expectLinkDropped($link, $user, McpLinkReason::PasswordChanged, $key, $credential);
});

it('drops the links when the user is deactivated', function () {
    // Created first: a call with the key switches the default guard, and permissions are guard-scoped.
    $admin = userWithUserAbilities(['update']);
    [$user, $link, $key, $credential] = linkedUserWithToken();
    Sanctum::actingAs($admin);

    $this->putJson("/api/users/{$user->id}", ['is_active' => false])->assertOk();

    expectLinkDropped($link, $user, McpLinkReason::UserDeactivated, $key, $credential);
});

it('leaves the links alone when something else changes, and other users links too', function () {
    [$user, $link] = linkedUserWithToken();
    [, $otherLink] = linkedUserWithToken();

    $user->forceFill(['name' => 'Renamed', 'password' => $user->password])->save();
    $user->forceFill(['password' => Hash::make('password')])->saveQuietly();

    expect($link->fresh()->revoked_at)->toBeNull()
        ->and($otherLink->fresh()->revoked_at)->toBeNull();
});

it('drops the links when the user chooses the first password', function () {
    [$user, $link, $key, $credential] = linkedUserWithToken();
    $user->forceFill(['must_set_password' => true])->saveQuietly();

    callWithToken('PUT', '/api/auth/me/first-password', $user->createToken('app')->plainTextToken, [
        'password' => 'Brand-New-Pass1!',
        'password_confirmation' => 'Brand-New-Pass1!',
    ])->assertOk();

    expectLinkDropped($link, $user, McpLinkReason::PasswordChanged, $key, $credential);
});

it('drops the links when the welcome credentials are sent again', function () {
    Notification::fake();
    // Created first: a call with the key switches the default guard, and permissions are guard-scoped.
    $admin = userWithUserAbilities(['update']);
    [$user, $link, $key, $credential] = linkedUserWithToken();
    Sanctum::actingAs($admin);

    $this->postJson("/api/users/{$user->id}/resend-welcome")->assertOk();

    expectLinkDropped($link, $user, McpLinkReason::PasswordChanged, $key, $credential);
});

it('drops the links when the daily sync deactivates a terminated user', function () {
    $user = EmploymentProfile::factory()->create(['terminated_at' => today()->toDateString()])->user;
    [$key, $credential, , $link] = openLinkFor($user);
    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertOk();

    $this->artisan('employment:daily-sync')->assertSuccessful();

    expectLinkDropped($link, $user, McpLinkReason::UserDeactivated, $key, $credential);
});

it('never breaks the password change nor the revocation of the other sessions when the drop fails', function () {
    [$user, , $key, $credential] = linkedUserWithToken();
    $current = $user->createToken('app');
    $other = $user->createToken('other');
    $this->partialMock(McpLinkService::class, fn ($mock) => $mock->shouldReceive('dropForUser')->andThrow(new RuntimeException('lock timeout')));

    callWithToken('PUT', '/api/auth/me/password', $current->plainTextToken, [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue()
        ->and($user->tokens()->whereKey($other->accessToken->id)->exists())->toBeFalse();

    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])
        ->assertUnauthorized()->assertJsonPath('data.reason', McpLinkReason::PasswordChanged->value);
});

it('never breaks the password reset when the drop fails', function () {
    [$user, , $key, $credential] = linkedUserWithToken();
    $this->partialMock(McpLinkService::class, fn ($mock) => $mock->shouldReceive('dropForUser')->andThrow(new RuntimeException('lock timeout')));

    $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => Password::createToken($user),
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expect($user->tokens()->count())->toBe(0);

    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])
        ->assertUnauthorized()->assertJsonPath('data.reason', McpLinkReason::PasswordChanged->value);
});

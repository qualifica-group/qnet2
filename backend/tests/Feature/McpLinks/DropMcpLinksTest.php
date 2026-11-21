<?php

use App\Models\McpLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/../../Helpers/McpLinkHelpers.php';

uses(RefreshDatabase::class);

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

function expectLinkDropped(McpLink $link, User $user, string $reason, string $key, string $credential): void
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

    expectLinkDropped($link, $user, McpLink::REVOKED_PASSWORD_CHANGED, $key, $credential);
});

it('drops the links when an administrator sets the password', function () {
    // Created first: a call with the key switches the default guard, and permissions are guard-scoped.
    $admin = userWithUserAbilities(['update']);
    [$user, $link, $key, $credential] = linkedUserWithToken();
    Sanctum::actingAs($admin);

    $this->putJson("/api/users/{$user->id}", ['password' => 'Admin-set-pass-123', 'password_confirmation' => 'Admin-set-pass-123'])->assertOk();

    expectLinkDropped($link, $user, McpLink::REVOKED_PASSWORD_CHANGED, $key, $credential);
});

it('drops the links when the password is reset', function () {
    [$user, $link, $key, $credential] = linkedUserWithToken();

    $this->postJson('/api/auth/reset-password', [
        'email' => $user->email,
        'token' => Password::createToken($user),
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk();

    expectLinkDropped($link, $user, McpLink::REVOKED_PASSWORD_CHANGED, $key, $credential);
});

it('drops the links when the user is deactivated', function () {
    // Created first: a call with the key switches the default guard, and permissions are guard-scoped.
    $admin = userWithUserAbilities(['update']);
    [$user, $link, $key, $credential] = linkedUserWithToken();
    Sanctum::actingAs($admin);

    $this->putJson("/api/users/{$user->id}", ['is_active' => false])->assertOk();

    expectLinkDropped($link, $user, McpLink::REVOKED_USER_DEACTIVATED, $key, $credential);
});

it('leaves the links alone when something else changes, and other users links too', function () {
    [$user, $link] = linkedUserWithToken();
    [, $otherLink] = linkedUserWithToken();

    $user->forceFill(['name' => 'Renamed', 'password' => $user->password])->save();
    $user->forceFill(['password' => Hash::make('password')])->saveQuietly();

    expect($link->fresh()->revoked_at)->toBeNull()
        ->and($otherLink->fresh()->revoked_at)->toBeNull();
});

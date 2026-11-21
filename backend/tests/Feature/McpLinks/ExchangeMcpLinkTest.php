<?php

use App\Models\McpLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

require_once __DIR__.'/../../Helpers/McpLinkHelpers.php';

uses(RefreshDatabase::class);

it('exchanges the credential for a short-lived user token bound to the connector client', function () {
    config(['mcp-link.release_commit' => 'abc1234']);
    [$key, $credential, $user, $link, $clientId] = openLinkFor();

    $response = callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.release_commit', 'abc1234')
        ->assertJsonPath('data.user.id', $user->id);

    $stored = $user->tokens()->firstOrFail();

    expect($stored->api_client_id)->toBe($clientId)
        ->and($stored->expires_at->isBetween(now()->addMinutes(59), now()->addMinutes(61)))->toBeTrue()
        ->and($link->tokens()->count())->toBe(1);

    callWithToken('GET', '/api/auth/me', $response->json('data.token'))->assertOk()->assertJsonPath('data.id', $user->id);
});

it('never issues a token that outlives the link', function () {
    [$key, $credential, $user, $link] = openLinkFor();
    $link->forceFill(['expires_at' => now()->addMinutes(10)])->save();

    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertOk();

    expect($user->tokens()->firstOrFail()->expires_at->lte(now()->addMinutes(10)))->toBeTrue();
});

it('refuses the exchange when the link cannot be used', function (Closure $break, string $reason) {
    [$key, $credential, $user, $link] = openLinkFor();
    $break($user, $link);

    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])
        ->assertUnauthorized()
        ->assertJsonPath('data.reason', $reason);

    expect($user->tokens()->count())->toBe(0);
})->with([
    'user deactivated' => [fn (User $u) => $u->forceFill(['is_active' => false])->saveQuietly(), 'user_inactive'],
    'password changed' => [fn (User $u) => $u->forceFill(['password' => Hash::make('other-secret')])->saveQuietly(), 'password_changed'],
    'link revoked' => [fn (User $u, McpLink $l) => $l->forceFill(['revoked_at' => now(), 'revoked_reason' => McpLink::REVOKED_BY_CLIENT])->save(), 'link_revoked'],
    'link expired' => [fn (User $u, McpLink $l) => $l->forceFill(['expires_at' => now()->subMinute()])->save(), 'link_expired'],
]);

it('refuses an unknown credential and a credential of another client', function () {
    [$key] = openLinkFor();
    [, $otherCredential] = openLinkFor();

    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => str_repeat('0', 64)])
        ->assertUnauthorized()->assertJsonPath('data.reason', 'link_unknown');
    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $otherCredential])
        ->assertUnauthorized()->assertJsonPath('data.reason', 'link_unknown');
});

it('cannot be reached with a user token', function () {
    [, $credential] = openLinkFor();
    [$client] = createApiClientWithKey();
    $userToken = createClientLoginToken(User::factory()->create(), $client);

    callWithToken('POST', '/api/mcp/links/exchange', $userToken, ['credential' => $credential])->assertForbidden();
});

it('revokes a link and deletes the user tokens it issued, keeping the others', function () {
    [$key, $credential, $user, $link, $clientId] = openLinkFor();
    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertOk();
    $unrelated = $user->createToken('app');

    callWithToken('DELETE', "/api/mcp/links/{$link->id}", $key)->assertOk();

    expect($link->fresh()->revoked_at)->not->toBeNull()
        ->and($link->fresh()->revoked_reason)->toBe(McpLink::REVOKED_BY_CLIENT)
        ->and($user->tokens()->pluck('id')->all())->toBe([$unrelated->accessToken->id]);

    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])
        ->assertUnauthorized()->assertJsonPath('data.reason', 'link_revoked');
});

it('does not revoke the link of another client and cannot be reached with a user token', function () {
    [, , , $link] = openLinkFor();
    [$otherClient, $otherKey] = createApiClientWithKey();

    callWithToken('DELETE', "/api/mcp/links/{$link->id}", $otherKey)->assertNotFound();
    expect($link->fresh()->revoked_at)->toBeNull();

    $userToken = createClientLoginToken(User::factory()->create(), $otherClient);
    callWithToken('DELETE', "/api/mcp/links/{$link->id}", $userToken)->assertForbidden();
});

<?php

use App\Enums\McpLinkReason;
use App\Models\McpLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('exchanges the credential for a short-lived user token bound to the connector client', function () {
    config(['mcp-link.release_commit' => 'abc1234']);
    [$key, $credential, $user, $link, $clientId] = openLinkFor();

    $response = callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.release_commit', 'abc1234')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.name', $user->name)
        ->assertJsonMissingPath('data.user.email');

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
    'user deactivated' => [fn (User $u) => $u->forceFill(['is_active' => false])->saveQuietly(), McpLinkReason::UserInactive->value],
    'password changed' => [fn (User $u) => $u->forceFill(['password' => Hash::make('other-secret')])->saveQuietly(), McpLinkReason::PasswordChanged->value],
    'link revoked' => [fn (User $u, McpLink $l) => $l->forceFill(['revoked_at' => now(), 'revoked_reason' => McpLinkReason::RevokedByClient])->save(), McpLinkReason::LinkRevoked->value],
    'link expired' => [fn (User $u, McpLink $l) => $l->forceFill(['expires_at' => now()->subMinute()])->save(), McpLinkReason::LinkExpired->value],
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

    callWithToken('POST', "/api/mcp/links/{$link->id}/revoke", $key)->assertOk();

    expect($link->fresh()->revoked_at)->not->toBeNull()
        ->and($link->fresh()->revoked_reason)->toBe(McpLinkReason::RevokedByClient)
        ->and($user->tokens()->pluck('id')->all())->toBe([$unrelated->accessToken->id]);

    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])
        ->assertUnauthorized()->assertJsonPath('data.reason', 'link_revoked');
});

it('does not revoke the link of another client and cannot be reached with a user token', function () {
    [, , , $link] = openLinkFor();
    [$otherClient, $otherKey] = createApiClientWithKey();

    callWithToken('POST', "/api/mcp/links/{$link->id}/revoke", $otherKey)->assertNotFound();
    expect($link->fresh()->revoked_at)->toBeNull();

    $userToken = createClientLoginToken(User::factory()->create(), $otherClient);
    callWithToken('POST', "/api/mcp/links/{$link->id}/revoke", $userToken)->assertForbidden();
});

it('declares an unset release commit as an explicit null', function () {
    config(['mcp-link.release_commit' => null]);
    [$key, $credential] = openLinkFor();

    $data = callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertOk()->json('data');

    expect($data)->toHaveKey('release_commit')
        ->and($data['release_commit'])->toBeNull();
});

it('revokes with a client that is allowed only the POST method', function () {
    [, $key] = createApiClientWithKey(['allowed_methods' => ['POST']]);
    $user = User::factory()->create();
    $linkId = callWithToken('POST', '/api/mcp/links', $key, [
        'email' => $user->email, 'password' => 'password', 'mcp_client' => 'test-mcp-client',
    ])->assertCreated()->json('data.link_id');

    callWithToken('POST', "/api/mcp/links/{$linkId}/revoke", $key)->assertOk();

    expect(McpLink::query()->findOrFail($linkId)->revoked_at)->not->toBeNull();
});

it('leaves no token behind when the exchange fails midway', function () {
    [$key, $credential, $user, $link] = openLinkFor();
    Event::listen('eloquent.updated: '.PersonalAccessToken::class, fn () => throw new RuntimeException('forced failure'));

    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertStatus(500);

    expect($user->tokens()->count())->toBe(0)
        ->and($link->tokens()->count())->toBe(0);
});

<?php

use App\Models\McpLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function openLinkBody(User $user, string $password = 'password'): array
{
    return ['email' => $user->email, 'password' => $password, 'mcp_client' => 'test-mcp-client'];
}

it('opens a link and keeps only the fingerprint of the credential', function () {
    [$client, $key] = createApiClientWithKey();
    $user = User::factory()->create();

    $response = callWithToken('POST', '/api/mcp/links', $key, openLinkBody($user))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.id', $user->id);

    $credential = $response->json('data.credential');
    $link = McpLink::query()->findOrFail($response->json('data.link_id'));

    expect($link->user_id)->toBe($user->id)
        ->and($link->api_client_id)->toBe($client->id)
        ->and($link->mcp_client)->toBe('test-mcp-client')
        ->and($link->credential_hash)->toBe(hash('sha256', $credential))
        ->and($link->credential_hash)->not->toBe($credential)
        ->and($link->expires_at->isBetween(now()->addDays(364), now()->addDays(366)))->toBeTrue()
        ->and($response->json('data.expires_at'))->toBe($link->expires_at->toIso8601String())
        ->and($user->tokens()->count())->toBe(0);
});

it('answers every credential failure with the same 422 message and opens nothing', function (Closure $makeUser, string $password) {
    [, $key] = createApiClientWithKey();
    $user = $makeUser();

    $messages = callWithToken('POST', '/api/mcp/links', $key, openLinkBody($user, $password))
        ->assertUnprocessable()
        ->json('errors.email');

    expect($messages)->toBe([__('auth.failed')])
        ->and(McpLink::query()->count())->toBe(0);
})->with([
    'wrong password' => [fn () => User::factory()->create(), 'wrong'],
    'inactive user' => [fn () => User::factory()->inactive()->create(), 'password'],
    'technical user' => [fn () => User::factory()->serviceAccount()->create(), 'password'],
]);

it('refuses while the first access is not completed', function () {
    [, $key] = createApiClientWithKey();
    $user = User::factory()->create();
    $user->forceFill(['must_set_password' => true])->save();

    callWithToken('POST', '/api/mcp/links', $key, openLinkBody($user))
        ->assertForbidden()
        ->assertJsonPath('data.reason', 'first_access_pending');

    expect(McpLink::query()->count())->toBe(0);
});

it('has its own attempt limit per account, configurable', function () {
    config(['mcp-link.open_attempts.max' => 2]);
    [, $key] = createApiClientWithKey();
    $user = User::factory()->create();
    $other = User::factory()->create();

    callWithToken('POST', '/api/mcp/links', $key, openLinkBody($user, 'wrong'))->assertUnprocessable();
    callWithToken('POST', '/api/mcp/links', $key, openLinkBody($user, 'wrong'))->assertUnprocessable();
    callWithToken('POST', '/api/mcp/links', $key, openLinkBody($user))->assertStatus(429);

    callWithToken('POST', '/api/mcp/links', $key, openLinkBody($other))->assertCreated();
});

it('cannot be reached with a user token or without the key', function () {
    [$client] = createApiClientWithKey();
    $user = User::factory()->create();

    $this->postJson('/api/mcp/links', openLinkBody($user))->assertUnauthorized();

    $appToken = User::factory()->create()->createToken('app')->plainTextToken;
    callWithToken('POST', '/api/mcp/links', $appToken, openLinkBody($user))->assertForbidden();

    $userToken = createClientLoginToken(User::factory()->create(), $client);
    callWithToken('POST', '/api/mcp/links', $userToken, openLinkBody($user))->assertForbidden();

    expect(McpLink::query()->count())->toBe(0);
});

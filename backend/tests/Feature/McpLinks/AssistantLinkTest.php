<?php

use App\Enums\McpLinkReason;
use App\Models\AssistantLink;
use App\Models\McpLink;
use App\Models\User;
use App\Services\McpLinks\AssistantLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['mcp-link.release_commit' => 'abc1234']));

/**
 * @return array{0: string, 1: User, 2: PersonalAccessToken, 3: string} connector key, user, session token, its plain text
 */
function assistantSession(?User $user = null): array
{
    [$client, $key] = createApiClientWithKey();
    config(['mcp-link.assistant_client_id' => $client->id]);
    $user ??= User::factory()->create();
    $session = $user->createToken('web');

    return [$key, $user, $session->accessToken, $session->plainTextToken];
}

function exchangeAssistant(string $key, string $credential)
{
    return callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential]);
}

it('opens a link for the connector client, marked as assistant, from the session token', function () {
    [$key, $user, $session] = assistantSession();

    $credential = app(AssistantLinkService::class)->credentialFor($user, $session);

    $link = McpLink::query()->where('user_id', $user->id)->sole();
    expect($credential)->toMatch('/^[0-9a-f]{64}$/')
        ->and($link->api_client_id)->toBe((int) config('mcp-link.assistant_client_id'))
        ->and($link->mcp_client)->toBe(config('mcp-link.assistant_mcp_client'))
        ->and($link->credential_hash)->toBe(McpLink::fingerprint($credential));

    exchangeAssistant($key, $credential)->assertOk()->assertJsonPath('data.user.id', $user->id);
});

it('keeps the credential encrypted at rest, one record per token, hidden from serialization', function () {
    [, $user, $session] = assistantSession();

    $credential = app(AssistantLinkService::class)->credentialFor($user, $session);

    $record = AssistantLink::query()->sole();
    expect(DB::table('assistant_links')->value('credential'))->not->toContain($credential)
        ->and($record->credential)->toBe($credential)
        ->and($record->personal_access_token_id)->toBe($session->id)
        ->and($record->toArray())->not->toHaveKey('credential')
        ->and($record->toJson())->not->toContain($credential);
});

it('reuses the link of the same token and opens another for another token', function () {
    [, $user, $session] = assistantSession();
    $service = app(AssistantLinkService::class);

    $first = $service->credentialFor($user, $session);
    $again = $service->credentialFor($user, $session);
    $other = $service->credentialFor($user, $user->createToken('phone')->accessToken);

    expect($again)->toBe($first)
        ->and($other)->not->toBe($first)
        ->and(McpLink::query()->count())->toBe(2);
});

it('falls with the session token, however the token ends', function (Closure $end) {
    [$key, $user, $session, $plain] = assistantSession();
    $credential = app(AssistantLinkService::class)->credentialFor($user, $session);
    $issued = exchangeAssistant($key, $credential)->assertOk()->json('data.token');

    $end($session, $user, $plain);

    exchangeAssistant($key, $credential)->assertUnauthorized()->assertJsonPath('data.reason', McpLinkReason::SessionEnded->value);
    callWithToken('GET', '/api/auth/me', $issued)->assertUnauthorized();
})->with([
    'logout (model delete)' => [fn (PersonalAccessToken $t) => $t->delete()],
    'revoked in bulk' => [fn (PersonalAccessToken $t, User $u) => $u->tokens()->whereKey($t->id)->delete()],
    'expired' => [fn (PersonalAccessToken $t) => $t->forceFill(['expires_at' => now()->subMinute()])->save()],
    'past SANCTUM_EXPIRATION' => [fn (PersonalAccessToken $t) => $t->forceFill(['created_at' => now()->subMinutes(config('sanctum.expiration') + 1)])->save()],
    'refreshed' => [fn (PersonalAccessToken $t, User $u, string $plain) => callWithToken('POST', '/api/auth/refresh', $plain)->assertOk()],
]);

it('opens a new link at the next turn, for the valid token, and retires the fallen one', function () {
    [$key, $user, $session, $plain] = assistantSession();
    $service = app(AssistantLinkService::class);
    $old = $service->credentialFor($user, $session);
    $newPlain = callWithToken('POST', '/api/auth/refresh', $plain)->assertOk()->json('data.token');
    $newToken = PersonalAccessToken::findToken($newPlain);

    $new = $service->credentialFor($user->fresh(), $newToken);

    expect($new)->not->toBe($old)
        ->and(AssistantLink::query()->count())->toBe(1)
        ->and(McpLink::query()->where('credential_hash', McpLink::fingerprint($old))->sole()->revoked_reason)->toBe(McpLinkReason::SessionEnded);
    exchangeAssistant($key, $new)->assertOk();
    exchangeAssistant($key, $old)->assertUnauthorized();
});

it('falls for today causes too: password changed, user deactivated, revoked by the client', function (Closure $cause, string $reason) {
    [$key, $user, $session] = assistantSession();
    $credential = app(AssistantLinkService::class)->credentialFor($user, $session);

    $cause($user, $key);

    exchangeAssistant($key, $credential)->assertUnauthorized()->assertJsonPath('data.reason', $reason);
})->with([
    'password changed' => [fn (User $u) => $u->update(['password' => Hash::make('another-secret')]), McpLinkReason::LinkRevoked->value],
    'user deactivated' => [fn (User $u) => $u->update(['is_active' => false]), McpLinkReason::LinkRevoked->value],
    'revoked' => [fn (User $u, string $key) => callWithToken('POST', '/api/mcp/links/'.McpLink::query()->value('id').'/revoke', $key)->assertSuccessful(), McpLinkReason::LinkRevoked->value],
]);

it('opens a new link after a fall of today causes, for the same token', function () {
    [$key, $user, $session] = assistantSession();
    $service = app(AssistantLinkService::class);
    $old = $service->credentialFor($user, $session);
    $user->update(['password' => Hash::make('another-secret')]);

    $new = $service->credentialFor($user->fresh(), $session);

    expect($new)->not->toBe($old);
    exchangeAssistant($key, $new)->assertOk();
});

it('refuses to open without a configured and active connector client', function (Closure $break) {
    [, $user, $session] = assistantSession();
    $break();

    expect(fn () => app(AssistantLinkService::class)->credentialFor($user, $session))->toThrow(RuntimeException::class);
    expect(McpLink::query()->count())->toBe(0);
})->with([
    'not configured' => [fn () => config(['mcp-link.assistant_client_id' => null])],
    'unknown client' => [fn () => config(['mcp-link.assistant_client_id' => 999999])],
    'inactive client' => [fn () => DB::table('api_clients')->where('id', config('mcp-link.assistant_client_id'))->update(['is_active' => false])],
]);

it('refuses a session that is not the one of that person', function (Closure $make) {
    [, $user, $session] = assistantSession();
    [$token, $holder] = $make($user, $session);

    expect(fn () => app(AssistantLinkService::class)->credentialFor($holder, $token))->toThrow(Exception::class);
    expect(McpLink::query()->count())->toBe(0);
})->with([
    'token of someone else' => [fn () => [User::factory()->create()->createToken('x')->accessToken, User::factory()->create()]],
    'expired token' => [fn (User $u) => [$u->createToken('old', ['*'], now()->subMinute())->accessToken, $u]],
    'token of a link' => [fn (User $u) => [$u->createToken('mcp-link-1', McpLink::tokenAbilities(1))->accessToken, $u]],
    'inactive user' => [function (User $u, PersonalAccessToken $t) {
        $u->forceFill(['is_active' => false])->saveQuietly();

        return [$t, $u];
    }],
    'first password pending' => [function (User $u, PersonalAccessToken $t) {
        $u->forceFill(['must_set_password' => true])->saveQuietly();

        return [$t, $u];
    }],
]);

it('never lets the credential reach logs, activity log or an exception message', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged): void {
        $logged[] = $e->message.json_encode($e->context);
    });
    [$key, $user, $session] = assistantSession();

    $credential = app(AssistantLinkService::class)->credentialFor($user, $session);
    exchangeAssistant($key, $credential)->assertOk();
    $session->delete();
    $rejected = exchangeAssistant($key, $credential)->assertUnauthorized()->getContent();

    expect(implode($logged))->not->toContain($credential)
        ->and($rejected)->not->toContain($credential)
        ->and(json_encode(DB::table('activity_log')->get()))->not->toContain($credential);
});

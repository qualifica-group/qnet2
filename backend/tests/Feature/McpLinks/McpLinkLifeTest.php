<?php

use App\Enums\McpLinkReason;
use App\Models\McpLink;
use App\Models\User;
use App\Services\AuthService;
use App\Services\McpLinks\EmailCollationKey;
use App\Services\McpLinks\McpLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['mcp-link.release_commit' => 'abc1234']));

/**
 * @return array{0: string, 1: string, 2: User, 3: McpLink, 4: string} key, credential, user, link, plain token
 */
function exchangedLink(?User $user = null): array
{
    [$key, $credential, $user, $link] = openLinkFor($user);
    $token = callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])->assertOk()->json('data.token');

    return [$key, $credential, $user, $link, $token];
}

function dropFails(): void
{
    $mock = Mockery::mock(McpLinkService::class, [app(AuthService::class), app(EmailCollationKey::class)])->makePartial();
    $mock->shouldReceive('dropForUser')->andThrow(new RuntimeException('lock timeout'));
    app()->instance(McpLinkService::class, $mock);
}

it('refuses to refresh a marked token even when its row of the bridge table is gone', function () {
    [, , $user, , $token] = exchangedLink();
    DB::table('mcp_link_tokens')->delete();

    callWithToken('POST', '/api/auth/refresh', $token)->assertForbidden();

    expect($user->tokens()->count())->toBe(1);
});

it('rejects and deletes a marked token whose link is no longer alive, whatever deleted the token or not', function (Closure $break) {
    [, , $user, $link, $token] = exchangedLink();
    $break($user, $link);

    callWithToken('GET', '/api/auth/me', $token)->assertUnauthorized();

    expect($user->tokens()->count())->toBe(0);
})->with([
    'link revoked' => [fn (User $u, McpLink $l) => $l->forceFill(['revoked_at' => now(), 'revoked_reason' => McpLinkReason::RevokedByClient])->save()],
    'link expired' => [fn (User $u, McpLink $l) => $l->forceFill(['expires_at' => now()->subMinute()])->save()],
    'password changed' => [fn (User $u) => $u->forceFill(['password' => Hash::make('other-secret')])->saveQuietly()],
]);

it('rejects a marked token of a deactivated user', function () {
    [, , $user, , $token] = exchangedLink();
    $user->forceFill(['is_active' => false])->saveQuietly();

    callWithToken('GET', '/api/auth/me', $token)->assertUnauthorized();
});

it('covers the broadcasting auth route too', function () {
    [, , $user, $link, $token] = exchangedLink();
    $link->forceFill(['revoked_at' => now(), 'revoked_reason' => McpLinkReason::RevokedByClient])->save();

    callWithToken('POST', '/api/broadcasting/auth', $token, ['channel_name' => 'private-x', 'socket_id' => '1.2'])->assertUnauthorized();

    expect($user->tokens()->count())->toBe(0);
});

it('rejects the old token on the next request when the drop failed at an administrator password change', function () {
    // Created first: a call with the key switches the default guard, and permissions are guard-scoped.
    $admin = userWithUserAbilities(['update']);
    [, , $user, , $token] = exchangedLink();
    dropFails();
    Sanctum::actingAs($admin);

    $this->putJson("/api/users/{$user->id}", ['password' => 'Admin-set-pass-123', 'password_confirmation' => 'Admin-set-pass-123'])->assertOk();

    callWithToken('GET', '/api/auth/me', $token)->assertUnauthorized();
    expect($user->tokens()->count())->toBe(0);
});

it('does not let a link survive a deactivation and reactivation even when the first drop failed', function () {
    $admin = userWithUserAbilities(['update']);
    [$key, $credential, $user, $link] = exchangedLink();
    dropFails();
    Sanctum::actingAs($admin);

    $this->putJson("/api/users/{$user->id}", ['is_active' => false])->assertOk();
    expect($link->fresh()->revoked_at)->toBeNull();

    $this->app->forgetInstance(McpLinkService::class);
    // Through the model: the API reactivation also needs business function coverage, unrelated here.
    $user->fresh()->update(['is_active' => true]);

    expect($link->fresh()->revoked_reason)->toBe(McpLinkReason::UserDeactivated);
    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])
        ->assertUnauthorized()->assertJsonPath('data.reason', McpLinkReason::LinkRevoked->value);
});

it('leaves a token with a name that only imitates the marker as an ordinary token', function () {
    [$client] = createApiClientWithKey();
    [, , , $someoneElsesLink] = openLinkFor();
    $user = User::factory()->create();

    $noLink = $user->createToken('mcp-link-999999')->plainTextToken;
    $notHis = createClientLoginToken($user, $client);
    $user->tokens()->latest('id')->first()->forceFill(['name' => 'mcp-link-'.$someoneElsesLink->id])->save();

    foreach ([$noLink, $notHis] as $token) {
        callWithToken('GET', '/api/auth/me', $token)->assertOk();
    }

    callWithToken('POST', '/api/auth/refresh', $noLink)->assertOk();
});

it('opens no live link when the user is deactivated between the check and the insert', function () {
    [, $key] = createApiClientWithKey();
    $user = User::factory()->create();
    $stale = User::query()->findOrFail($user->id);
    $this->partialMock(AuthService::class, fn ($mock) => $mock->shouldReceive('authenticate')->andReturnUsing(function () use ($stale) {
        DB::table('users')->where('id', $stale->id)->update(['is_active' => false]);

        return $stale;
    }));

    callWithToken('POST', '/api/mcp/links', $key, ['email' => $user->email, 'password' => 'password', 'mcp_client' => 'c'])
        ->assertUnprocessable();

    expect(McpLink::query()->count())->toBe(0);
});

it('answers the same 429 for an address without account as for an existing account, with its own counter', function () {
    config(['mcp-link.open_attempts.max' => 2]);
    [, $key] = createApiClientWithKey();
    $user = User::factory()->create();
    $body = fn (string $email) => ['email' => $email, 'password' => 'wrong', 'mcp_client' => 'c'];

    foreach ([$user->email, 'Ghost@Example.test'] as $email) {
        callWithToken('POST', '/api/mcp/links', $key, $body($email))->assertUnprocessable();
        callWithToken('POST', '/api/mcp/links', $key, $body($email))->assertUnprocessable();
    }

    $existing = callWithToken('POST', '/api/mcp/links', $key, $body($user->email))->assertStatus(429);
    $ghost = callWithToken('POST', '/api/mcp/links', $key, $body('ghost@example.test'))->assertStatus(429);

    expect($ghost->json())->toBe($existing->json());
});

it('caps the failed openings per API client, whatever the addresses', function () {
    config(['mcp-link.open_attempts.client_max' => 2]);
    [, $key] = createApiClientWithKey();
    [, $otherKey] = createApiClientWithKey();
    $user = User::factory()->create();

    foreach (['a@example.test', 'b@example.test'] as $email) {
        callWithToken('POST', '/api/mcp/links', $key, ['email' => $email, 'password' => 'x', 'mcp_client' => 'c'])->assertUnprocessable();
    }

    callWithToken('POST', '/api/mcp/links', $key, ['email' => $user->email, 'password' => 'password', 'mcp_client' => 'c'])->assertStatus(429);
    callWithToken('POST', '/api/mcp/links', $otherKey, ['email' => $user->email, 'password' => 'password', 'mcp_client' => 'c'])->assertCreated();
});

it('costs no query on the links table for an ordinary token and one for a marked token', function () {
    [, , , , $marked] = exchangedLink();
    $ordinary = User::factory()->create()->createToken('app')->plainTextToken;
    $linkQueries = 0;
    DB::listen(function ($query) use (&$linkQueries) {
        $linkQueries += str_contains($query->sql, 'from "mcp_links"') || str_contains($query->sql, 'from `mcp_links`') ? 1 : 0;
    });

    callWithToken('GET', '/api/auth/me', $ordinary)->assertOk();
    expect($linkQueries)->toBe(0);

    callWithToken('GET', '/api/auth/me', $marked)->assertOk();
    expect($linkQueries)->toBe(1);
});

it('keeps a link fallen across a deactivation and a reactivation while the token deletion keeps failing', function () {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('The failing deletion is simulated with a sqlite trigger.');
    }

    [$key, $credential, $user, $link, $token] = exchangedLink();
    DB::unprepared("CREATE TRIGGER deny_token_delete BEFORE DELETE ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'denied'); END");

    $user->update(['is_active' => false]);
    expect($link->fresh()->revoked_at)->not->toBeNull();

    $user->fresh()->update(['is_active' => true]);

    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])
        ->assertUnauthorized()->assertJsonPath('data.reason', McpLinkReason::LinkRevoked->value);
    callWithToken('GET', '/api/auth/me', $token)->assertUnauthorized();

    DB::unprepared('DROP TRIGGER deny_token_delete');
});

it('keeps the old token rejected after a password change while the token deletion keeps failing', function () {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('The failing deletion is simulated with a sqlite trigger.');
    }

    [, , $user, $link, $token] = exchangedLink();
    DB::unprepared("CREATE TRIGGER deny_token_delete BEFORE DELETE ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'denied'); END");

    $user->forceFill(['password' => 'Another-pass-123'])->save();

    expect($link->fresh()->revoked_at)->not->toBeNull();
    callWithToken('GET', '/api/auth/me', $token)->assertUnauthorized();

    DB::unprepared('DROP TRIGGER deny_token_delete');
});

it('does not turn an ordinary client-login token into a link token through the device name', function () {
    [$key, $credential, $user, $link] = exchangedLink();

    $ordinary = callWithToken('POST', '/api/auth/client-login', $key, [
        'email' => $user->email, 'password' => 'password', 'device_name' => McpLink::tokenName($link->id),
    ])->assertOk()->json('data.token');

    callWithToken('POST', "/api/mcp/links/{$link->id}/revoke", $key)->assertOk();

    callWithToken('GET', '/api/auth/me', $ordinary)->assertOk();
    callWithToken('POST', '/api/auth/refresh', $ordinary)->assertOk();
});

it('gives the same answers for an existing and a missing account whatever the spelling of the address', function () {
    config(['mcp-link.open_attempts.max' => 2]);
    [, $key] = createApiClientWithKey();
    $user = User::factory()->create(['email' => 'mario@example.test']);
    $attempt = fn (string $email) => callWithToken('POST', '/api/mcp/links', $key, ['email' => $email, 'password' => 'wrong', 'mcp_client' => 'c'])->status();

    $existing = [$attempt('mario@example.test'), $attempt('mario@example.test'), $attempt('MARIO@example.test')];
    $missing = [$attempt('luigi@example.test'), $attempt('luigi@example.test'), $attempt('LUIGI@example.test')];

    expect($existing)->toBe([422, 422, 429])
        ->and($missing)->toBe($existing);
});

it('counts the accented spelling of an address on the same counter, existing account or not (MySQL collation)', function (string $email, string $accented) {
    if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
        $this->markTestSkipped('Accent equivalence belongs to the MySQL collation of the email column.');
    }

    config(['mcp-link.open_attempts.max' => 2]);
    [, $key] = createApiClientWithKey();
    User::factory()->create(['email' => 'mario@example.test']);
    $attempt = fn (string $address) => callWithToken('POST', '/api/mcp/links', $key, ['email' => $address, 'password' => 'wrong', 'mcp_client' => 'c'])->status();

    expect([$attempt($email), $attempt($email), $attempt($accented)])->toBe([422, 422, 429]);
})->with([
    'existing' => ['mario@example.test', 'màrio@example.test'],
    'missing' => ['luigi@example.test', 'lùigi@example.test'],
]);

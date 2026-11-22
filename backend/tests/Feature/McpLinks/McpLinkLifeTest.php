<?php

use App\Enums\McpLinkReason;
use App\Models\McpLink;
use App\Models\User;
use App\Services\AuthService;
use App\Services\McpLinks\EmailCollationKey;
use App\Services\McpLinks\McpLinkService;
use App\Services\McpLinks\OpenAttemptCounter;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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
    $mock = Mockery::mock(McpLinkService::class, [app(AuthService::class), app(EmailCollationKey::class), app(OpenAttemptCounter::class)])->makePartial();
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

it('refuses a deactivation when the drop fails, so the user never ends up deactivated with live links', function () {
    $admin = userWithUserAbilities(['update']);
    [, , $user, $link] = exchangedLink();
    dropFails();
    Sanctum::actingAs($admin);

    $this->putJson("/api/users/{$user->id}", ['is_active' => false])->assertStatus(500);

    expect($user->fresh()->is_active)->toBeTrue()
        ->and($link->fresh()->revoked_at)->toBeNull();
});

it('refuses a deactivation without transaction when the mark fails, leaving the user active', function () {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('The failing mark is simulated with a sqlite trigger.');
    }

    [, , $user, $link] = exchangedLink();
    DB::unprepared("CREATE TRIGGER deny_link_update BEFORE UPDATE ON mcp_links BEGIN SELECT RAISE(ABORT, 'denied'); END");

    expect(fn () => $user->update(['is_active' => false]))->toThrow(QueryException::class);
    expect($user->fresh()->is_active)->toBeTrue()
        ->and($link->fresh()->revoked_at)->toBeNull();

    DB::unprepared('DROP TRIGGER deny_link_update');
});

it('completes a password change when the mark fails, and the old token is rejected anyway', function () {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('The failing mark is simulated with a sqlite trigger.');
    }

    [$key, $credential, $user, , $token] = exchangedLink();
    DB::unprepared("CREATE TRIGGER deny_link_update BEFORE UPDATE ON mcp_links BEGIN SELECT RAISE(ABORT, 'denied'); END");

    $user->forceFill(['password' => 'Another-pass-123'])->save();

    callWithToken('GET', '/api/auth/me', $token)->assertUnauthorized();
    callWithToken('POST', '/api/mcp/links/exchange', $key, ['credential' => $credential])
        ->assertUnauthorized()->assertJsonPath('data.reason', McpLinkReason::PasswordChanged->value);

    DB::unprepared('DROP TRIGGER deny_link_update');
});

it('propagates an error that invalidates the transaction, even at a password change', function () {
    [, , $user] = exchangedLink();
    $mock = Mockery::mock(McpLinkService::class, [app(AuthService::class), app(EmailCollationKey::class), app(OpenAttemptCounter::class)])->makePartial();
    $mock->shouldReceive('dropForUser')->andThrow(new DeadlockException('Deadlock found'));
    app()->instance(McpLinkService::class, $mock);

    expect(fn () => $user->forceFill(['password' => 'Another-pass-123'])->save())->toThrow(DeadlockException::class);
});

it('tells the errors that invalidate the caller transaction from the ordinary ones', function (Throwable $error, bool $invalidates) {
    expect(McpLinkService::invalidatesTransaction($error))->toBe($invalidates);
})->with([
    'deadlock' => [new DeadlockException('Deadlock found'), true],
    'lost connection' => [new RuntimeException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'), true],
    'transaction already ended' => [new PDOException('There is no active transaction'), true],
    'ordinary' => [new RuntimeException('denied'), false],
]);

it('takes no lock on the bridge table when it drops a link, in the order of a logout', function () {
    [$key, , , $link] = exchangedLink();
    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = $query->sql;
    });

    callWithToken('POST', "/api/mcp/links/{$link->id}/revoke", $key)->assertOk();

    expect(collect($statements)->filter(fn (string $sql) => str_contains($sql, 'mcp_link_tokens')))->toBeEmpty();
});

it('reserves the attempt before the password check, so a concurrent request is never verified', function () {
    config(['mcp-link.open_attempts.max' => 2]);
    [, $key] = createApiClientWithKey();
    $user = User::factory()->create();
    $body = ['email' => $user->email, 'password' => 'wrong', 'mcp_client' => 'c'];
    $calls = 0;
    $nested = null;
    $this->partialMock(AuthService::class, function ($mock) use (&$calls, &$nested, $key, $body) {
        $mock->shouldReceive('authenticate')->andReturnUsing(function () use (&$calls, &$nested, $key, $body) {
            $calls++;

            // The second verification is still running when a concurrent request arrives.
            if ($calls === 2) {
                $nested = callWithToken('POST', '/api/mcp/links', $key, $body)->status();
            }

            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        });
    });

    callWithToken('POST', '/api/mcp/links', $key, $body)->assertUnprocessable();
    callWithToken('POST', '/api/mcp/links', $key, $body)->assertUnprocessable();

    expect($calls)->toBe(2)
        ->and($nested)->toBe(429);
});

it('gives the attempt back after a successful opening', function () {
    config(['mcp-link.open_attempts.max' => 2, 'mcp-link.open_attempts.client_max' => 2]);
    [, $key] = createApiClientWithKey();
    $user = User::factory()->create();

    foreach (range(1, 5) as $ignored) {
        callWithToken('POST', '/api/mcp/links', $key, ['email' => $user->email, 'password' => 'password', 'mcp_client' => 'c'])->assertCreated();
    }
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

it('keeps the attempt counter atomic: a request that arrives between the read and the write is refused, not verified', function () {
    config([
        'mcp-link.open_attempts.max' => 1,
        'mcp-link.open_attempts.counter_lock_wait_seconds' => 0,
    ]);
    // Only the counter store becomes the interleaving one; the lock stays on the default store.
    app()->instance(OpenAttemptCounter::class, new OpenAttemptCounter(Cache::repository(new InterleavingArrayStore)));

    [, $key] = createApiClientWithKey();
    $user = User::factory()->create();
    $body = ['email' => $user->email, 'password' => 'wrong', 'mcp_client' => 'c'];
    $calls = 0;
    $concurrent = null;
    $this->partialMock(AuthService::class, function ($mock) use (&$calls) {
        $mock->shouldReceive('authenticate')->andReturnUsing(function () use (&$calls) {
            $calls++;

            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        });
    });
    InterleavingArrayStore::$between = function () use (&$concurrent, $key, $body) {
        $concurrent = callWithToken('POST', '/api/mcp/links', $key, $body)->status();
    };

    callWithToken('POST', '/api/mcp/links', $key, $body)->assertUnprocessable();

    expect($calls)->toBe(1)
        ->and($concurrent)->toBe(429);
});

it('answers a failed explicit revocation with an error the connector can retry, and completes it the second time', function () {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('The failing deletion is simulated with a sqlite trigger.');
    }

    [$key, , $user, $link, $token] = exchangedLink();
    DB::unprepared("CREATE TRIGGER deny_token_delete BEFORE DELETE ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'denied'); END");

    callWithToken('POST', "/api/mcp/links/{$link->id}/revoke", $key)
        ->assertStatus(500)
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.reason', McpLinkReason::TokensNotDeleted->value);

    expect($link->fresh()->revoked_at)->not->toBeNull();
    callWithToken('GET', '/api/auth/me', $token)->assertUnauthorized();

    DB::unprepared('DROP TRIGGER deny_token_delete');

    callWithToken('POST', "/api/mcp/links/{$link->id}/revoke", $key)->assertOk();
    expect($user->tokens()->count())->toBe(0);
});

/**
 * Opens with a scripted authenticate: each call runs the next step (a failure, or a valid
 * answer for the user) so a test can place events in the middle of a verification.
 *
 * @param  array<int, Closure>  $steps
 */
function scriptAuthenticate(array $steps, int &$calls): void
{
    $mock = Mockery::mock(AuthService::class)->makePartial();
    $mock->shouldReceive('authenticate')->andReturnUsing(function () use ($steps, &$calls) {
        return $steps[$calls++]();
    });
    app()->instance(AuthService::class, $mock);
}

function failedVerification(): never
{
    throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
}

it('does not give back an attempt to a counter window that is not the one it was reserved in', function () {
    config(['mcp-link.open_attempts.max' => 100, 'mcp-link.open_attempts.client_max' => 2]);
    [, $key] = createApiClientWithKey();
    $user = User::factory()->create();
    $body = ['email' => $user->email, 'password' => 'password', 'mcp_client' => 'c'];
    $open = fn () => callWithToken('POST', '/api/mcp/links', $key, $body);
    $calls = 0;
    $start = now()->startOfSecond();

    scriptAuthenticate([
        fn () => failedVerification(),                       // opens the window at the client counter: 1
        function () use ($start, $open, $user) {             // the valid opening: reserves the 2nd attempt, then...
            $this->travelTo($start->copy()->addMinutes(16)); // ...the window expires while it verifies
            $open()->assertUnprocessable();                  // a wrong attempt starts the new window: counter 1

            return User::query()->findOrFail($user->id);
        },
        fn () => failedVerification(),                       // the wrong attempt above
        fn () => failedVerification(),                       // the 2nd in the new window: verified (counter 2)
        fn () => failedVerification(),                       // must never run: the 3rd is over the limit
    ], $calls);

    $open()->assertUnprocessable();                          // call 0
    $open()->assertCreated();                                // call 1 (+ nested call 2), late refund must change nothing
    $open()->assertUnprocessable();                          // call 3: counter 2, verified
    $open()->assertStatus(429);                              // counter 3: refused, no verification

    expect($calls)->toBe(4);
    $this->travelBack();
});

it('gives the attempt back when the opening succeeds in the same window', function () {
    config(['mcp-link.open_attempts.max' => 100, 'mcp-link.open_attempts.client_max' => 2]);
    [, $key] = createApiClientWithKey();
    $user = User::factory()->create();
    $body = ['email' => $user->email, 'password' => 'password', 'mcp_client' => 'c'];
    $open = fn () => callWithToken('POST', '/api/mcp/links', $key, $body);
    $calls = 0;

    scriptAuthenticate([
        fn () => failedVerification(),
        fn () => User::query()->findOrFail($user->id),
        fn () => failedVerification(),
        fn () => failedVerification(),
    ], $calls);

    $open()->assertUnprocessable();   // counter 1
    $open()->assertCreated();         // counter 2, then refunded: 1
    $open()->assertUnprocessable();   // counter 2: verified
    $open()->assertStatus(429);       // counter 3: refused

    expect($calls)->toBe(3);
});

it('never gives an attempt back to a later window, whatever the order in which earlier windows ended', function (int $max, int $clientMax) {
    config(['mcp-link.open_attempts.max' => $max, 'mcp-link.open_attempts.client_max' => $clientMax]);
    [, $key] = createApiClientWithKey();
    $user = User::factory()->create();
    $body = ['email' => $user->email, 'password' => 'password', 'mcp_client' => 'c'];
    $open = fn () => callWithToken('POST', '/api/mcp/links', $key, $body);
    $calls = 0;
    $start = now()->startOfSecond();
    $at = fn (int $seconds) => $this->travelTo($start->copy()->addSeconds($seconds));
    $limit = min($max, $clientMax);
    $wrongAttemptsAfterTheWindowChanged = $limit + 1;

    scriptAuthenticate([
        fn () => User::query()->findOrFail($user->id),       // t=0: a valid opening, given back
        fn () => failedVerification(),                       // t=800
        fn () => failedVerification(),                       // t=901
        function () use ($at, $open, $user, $limit) {        // t=1699: a valid opening that is still verifying...
            $at(1705);                                       // ...while wrong attempts fill the counter
            foreach (range(1, $limit) as $ignored) {
                $open();
            }

            return User::query()->findOrFail($user->id);
        },
        ...array_fill(0, $wrongAttemptsAfterTheWindowChanged, fn () => failedVerification()),
    ], $calls);
    $callsBeforeTheWrongAttempts = 4;

    $at(0);
    $open()->assertCreated();
    $at(800);
    $open()->assertUnprocessable();
    $at(901);
    $open()->assertUnprocessable();
    $at(1699);
    $open()->assertCreated();                                // its late refund must not free a slot
    $open()->assertStatus(429);

    // From t=1705 on, one window: at most `limit` wrong passwords are ever checked in it.
    expect($calls - $callsBeforeTheWrongAttempts)->toBeLessThanOrEqual($limit);
    $this->travelBack();
})->with([
    'account counter' => [5, 100],
    'client counter' => [100, 5],
]);

it('reports a connection lost while the tokens of an explicit revocation are deleted as tokens_not_deleted', function () {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('The lost connection is simulated with a sqlite trigger.');
    }

    [$key, , , $link] = exchangedLink();
    DB::unprepared("CREATE TRIGGER lose_connection BEFORE DELETE ON personal_access_tokens BEGIN SELECT RAISE(ABORT, 'MySQL server has gone away'); END");

    callWithToken('POST', "/api/mcp/links/{$link->id}/revoke", $key)
        ->assertStatus(500)
        ->assertJsonPath('data.reason', McpLinkReason::TokensNotDeleted->value);

    expect($link->fresh()->revoked_at)->not->toBeNull();

    DB::unprepared('DROP TRIGGER lose_connection');
});

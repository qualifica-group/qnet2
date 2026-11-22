<?php

namespace App\Services\McpLinks;

use App\DataObjects\McpLinks\ExchangedMcpLink;
use App\DataObjects\McpLinks\OpenedMcpLink;
use App\Enums\McpLinkReason;
use App\Models\ApiClient;
use App\Models\McpLink;
use App\Models\User;
use App\Services\AuthService;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Database\LostConnectionDetector;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Opening, exchange and revocation of MCP links. The credential is returned
 * once; only its SHA-256 fingerprint is stored.
 *
 * Lock order everywhere (open, exchange, revoke, drop): the user row, then the
 * link row, then the token rows. That is the order a logout already follows
 * (token row first, then its bridge row by cascade), so no lock is ever taken on
 * the bridge table directly. Reads after a lock are locking (current) reads.
 */
class McpLinkService
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly EmailCollationKey $emailKey,
    ) {}

    /**
     * @throws ValidationException
     * @throws TooManyOpenAttemptsException
     * @throws FirstAccessPendingException
     */
    public function open(ApiClient $client, string $email, string $password, string $mcpClient): OpenedMcpLink
    {
        // Step 1: reserve the attempt, atomically, before any password check
        $limits = $this->openLimits($client, $email);
        $reservations = $this->reserveAttempt($limits);

        // Step 2: same credential rules and generic failure as client-login
        $user = $this->auth->authenticate($email, $password, revealInactive: false);

        // Step 3: the link is created on the locked user row
        $opened = DB::transaction(fn (): OpenedMcpLink => $this->createLink($client, $user, $mcpClient));

        // Step 4: only a successful opening gives the attempt back
        $this->releaseAttempt($limits, $reservations);

        return $opened;
    }

    /**
     * Atomic: the user and the link rows are locked while the link is checked and
     * the token is created and attached, so a concurrent revocation or credential
     * change either comes first (the exchange is refused) or last (it deletes the
     * new token), and a failure midway leaves no token behind.
     *
     * @throws McpLinkRejectedException
     * @throws ReleaseCommitNotConfiguredException
     */
    public function exchange(ApiClient $client, string $credential): ExchangedMcpLink
    {
        // The contract returns the commit of QNET2 in service: without it no token is issued.
        $releaseCommit = config('mcp-link.release_commit');

        if (! filled($releaseCommit)) {
            throw new ReleaseCommitNotConfiguredException;
        }

        // Step 1: the link must be one of this client (plain read, before any lock)
        $probe = McpLink::query()
            ->where('credential_hash', McpLink::fingerprint($credential))
            ->where('api_client_id', $client->id)
            ->first() ?? throw new McpLinkRejectedException(McpLinkReason::LinkUnknown);

        return DB::transaction(function () use ($client, $probe, $releaseCommit): ExchangedMcpLink {
            // Lock order: user, then link
            $user = User::query()->lockForUpdate()->find($probe->user_id);
            $link = McpLink::query()->lockForUpdate()->find($probe->id);

            // Step 2: revoked, expired, user gone or inactive, password changed since opening
            match (true) {
                $link === null || $user === null => throw new McpLinkRejectedException(McpLinkReason::LinkUnknown),
                $link->revoked_at !== null => throw new McpLinkRejectedException(McpLinkReason::LinkRevoked),
                $link->expires_at->isPast() => throw new McpLinkRejectedException(McpLinkReason::LinkExpired),
                ! $user->is_active => throw new McpLinkRejectedException(McpLinkReason::UserInactive),
                ! $link->isAliveFor($user) => throw new McpLinkRejectedException(McpLinkReason::PasswordChanged),
                default => null,
            };

            // Step 3: short-lived token, never beyond the link, bound to the connector client
            $expiresAt = now()->addMinutes((int) config('mcp-link.access_token_ttl_minutes'));
            $expiresAt = $expiresAt->greaterThan($link->expires_at) ? $link->expires_at->copy() : $expiresAt;

            // The `mcp-link:<id>` ability is the marker EnforceMcpLinkLife reads.
            $newToken = $user->createToken(McpLink::tokenName($link->id), McpLink::tokenAbilities($link->id), $expiresAt);
            $newToken->accessToken->forceFill(['api_client_id' => $client->id])->save();
            $link->tokens()->attach($newToken->accessToken->id);

            return new ExchangedMcpLink($user, $newToken->plainTextToken, $expiresAt, $releaseCommit);
        });
    }

    /**
     * Revoke a link of this client and delete the user tokens it issued. The mark is
     * confirmed first; if the deletion then fails the error is thrown so the client
     * repeats the revocation (an already revoked link just completes the deletion).
     *
     * @throws McpLinkTokensNotDeletedException
     */
    public function revoke(ApiClient $client, int $linkId): void
    {
        $this->drop(McpLink::query()->where('api_client_id', $client->id)->findOrFail($linkId), McpLinkReason::RevokedByClient, failOnTokenDeletion: true);
    }

    /**
     * Mark every live link of the user as fallen and delete the user tokens they issued.
     */
    public function dropForUser(User $user, McpLinkReason $reason): void
    {
        DB::transaction(function () use ($user, $reason): void {
            // Lock order: user, then links
            User::query()->lockForUpdate()->find($user->id);

            McpLink::query()->where('user_id', $user->id)->whereNull('revoked_at')->lockForUpdate()->get()
                ->each(fn (McpLink $link) => $this->drop($link, $reason));
        });
    }

    /**
     * Step 1 marks the link fallen, durably and on its own: what keeps a link out of
     * use is that mark, which the exchange and EnforceMcpLinkLife both check, and a
     * failure of it propagates. Step 2 deleting the tokens is best effort: the
     * tokens of a fallen link are refused anyway, so an ordinary failure is reported
     * (and thrown only by an explicit revocation, which promises the deletion),
     * but an error that invalidates the caller's transaction (see
     * invalidatesTransaction) always propagates. The tokens are found by their
     * `mcp-link:<id>` ability with one DELETE, a current read: under REPEATABLE READ
     * a plain SELECT would use the snapshot of an earlier read of the caller's
     * transaction and miss a token the exchange committed meanwhile.
     */
    private function drop(McpLink $link, McpLinkReason $reason, bool $failOnTokenDeletion = false): void
    {
        DB::transaction(function () use ($link, $reason): void {
            User::query()->lockForUpdate()->find($link->user_id);
            $locked = McpLink::query()->lockForUpdate()->find($link->id);

            if ($locked !== null && $locked->revoked_at === null) {
                $locked->forceFill(['revoked_at' => now(), 'revoked_reason' => $reason])->save();
            }
        });

        try {
            DB::transaction(function () use ($link): void {
                $user = User::query()->lockForUpdate()->find($link->user_id);
                McpLink::query()->lockForUpdate()->find($link->id);

                $user?->tokens()->where('abilities', 'like', '%"'.McpLink::ABILITY_PREFIX.$link->id.'"%')->delete();
            });
        } catch (Throwable $e) {
            // No caller transaction in an explicit revocation: the failure is only a deletion to repeat.
            if (self::invalidatesTransaction($e) && ! $failOnTokenDeletion) {
                throw $e;
            }

            report($e);

            if ($failOnTokenDeletion) {
                throw new McpLinkTokensNotDeletedException($e);
            }
        }
    }

    /**
     * An error after which the caller's transaction is gone or in doubt (a deadlock, which
     * Laravel raises as DeadlockException inside a nested transaction; a lost connection;
     * a transaction already ended): swallowing it would let the caller go on in autocommit.
     */
    public static function invalidatesTransaction(Throwable $e): bool
    {
        return $e instanceof DeadlockException
            || app(LostConnectionDetector::class)->causedByLostConnection($e)
            || str_contains(strtolower($e->getMessage()), 'no active transaction');
    }

    private function createLink(ApiClient $client, User $authenticated, string $mcpClient): OpenedMcpLink
    {
        // Lock order: user first. Everything is re-checked on the locked row.
        $user = User::query()->lockForUpdate()->find($authenticated->id);

        if ($user === null || ! $user->is_active || $user->isServiceAccount() || $user->password !== $authenticated->password) {
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }

        // No link before the user has chosen the first password. Stricter than
        // isFirstAccessPending(), which still lets the user in while the choice is optional.
        if ($user->must_set_password) {
            throw new FirstAccessPendingException;
        }

        $credential = bin2hex(random_bytes((int) config('mcp-link.credential_bytes')));
        $link = McpLink::query()->create([
            'user_id' => $user->id,
            'api_client_id' => $client->id,
            'mcp_client' => $mcpClient,
            'credential_hash' => McpLink::fingerprint($credential),
            'password_fingerprint' => McpLink::fingerprint($user->password),
            'expires_at' => now()->addDays((int) config('mcp-link.link_ttl_days')),
        ]);

        return new OpenedMcpLink($link, $credential, $user);
    }

    /**
     * Counters of the failed openings: one per address as the database compares
     * addresses (see EmailCollationKey), the same whether the account exists or not,
     * plus a cap per API client so that a spray of addresses is bounded.
     *
     * @return array{account: array{key: string, max: int}, client: array{key: string, max: int}, decay: int}
     */
    private function openLimits(ApiClient $client, string $email): array
    {
        return [
            'account' => [
                'key' => 'mcp-link-open:email:'.sha1($this->emailKey->for($email)),
                'max' => (int) config('mcp-link.open_attempts.max'),
            ],
            'client' => [
                'key' => "mcp-link-open:client:{$client->id}",
                'max' => (int) config('mcp-link.open_attempts.client_max'),
            ],
            'decay' => (int) config('mcp-link.open_attempts.decay_minutes') * 60,
        ];
    }

    /**
     * The limiter's `hit` is add + increment + sometimes put, not one atomic operation,
     * so concurrent requests could lose updates and exceed the limit. Every change of a
     * counter therefore runs under a cache lock named after the counter; a request that
     * cannot get the lock in a short time is refused (the cautious side). Beyond the limit
     * no password is checked. Both counters are always incremented, one lock at a time.
     * Each reservation remembers the window (generation) of the counter it counted in.
     *
     * @param  array{account: array{key: string, max: int}, client: array{key: string, max: int}, decay: int}  $limits
     * @return array<string, int> window end per counter key
     *
     * @throws TooManyOpenAttemptsException
     */
    private function reserveAttempt(array $limits): array
    {
        $reservations = [];
        $retryAfter = collect();

        foreach ([$limits['account'], $limits['client']] as $limit) {
            [$hits, $window] = $this->underLock($limit['key'], fn (): array => [
                RateLimiter::hit($limit['key'], $limits['decay']),
                $this->windowEnd($limit['key']),
            ]);

            $reservations[$limit['key']] = $window;

            if ($hits > $limit['max']) {
                $retryAfter->push(RateLimiter::availableIn($limit['key']));
            }
        }

        if ($retryAfter->isNotEmpty()) {
            throw new TooManyOpenAttemptsException($retryAfter->max());
        }

        return $reservations;
    }

    /**
     * Gives the attempt back only if the counter is still the window it was reserved in:
     * a window that expired meanwhile and was started again by someone else is not ours.
     *
     * @param  array{account: array{key: string, max: int}, client: array{key: string, max: int}, decay: int}  $limits
     * @param  array<string, int>  $reservations
     */
    private function releaseAttempt(array $limits, array $reservations): void
    {
        foreach ([$limits['account'], $limits['client']] as $limit) {
            try {
                $this->underLock($limit['key'], function () use ($limit, $limits, $reservations): void {
                    if (! $this->sameWindow($limit['key'], $reservations[$limit['key']])) {
                        return;
                    }

                    // A counter that expired meanwhile would go negative and grant extra attempts.
                    if (RateLimiter::decrement($limit['key'], $limits['decay']) < 0) {
                        RateLimiter::clear($limit['key']);
                    }
                });
            } catch (TooManyOpenAttemptsException) {
                // Not given back: the attempt stays counted, the cautious side.
            }
        }
    }

    /**
     * The instant the window of a counter ends (the limiter keeps it as the counter's timer).
     */
    private function windowEnd(string $counterKey): int
    {
        return now()->getTimestamp() + RateLimiter::availableIn($counterKey);
    }

    /**
     * Windows are minutes apart; the tolerance only absorbs a second boundary between two reads.
     */
    private function sameWindow(string $counterKey, int $reservedWindowEnd): bool
    {
        return RateLimiter::availableIn($counterKey) > 0
            && abs($this->windowEnd($counterKey) - $reservedWindowEnd) <= (int) config('mcp-link.open_attempts.window_tolerance_seconds');
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     *
     * @throws TooManyOpenAttemptsException when the lock is not obtained in time
     */
    private function underLock(string $counterKey, Closure $change): mixed
    {
        try {
            return Cache::lock('lock:'.$counterKey, (int) config('mcp-link.open_attempts.counter_lock_seconds'))
                ->block((int) config('mcp-link.open_attempts.counter_lock_wait_seconds'), $change);
        } catch (LockTimeoutException) {
            throw new TooManyOpenAttemptsException(1);
        }
    }
}

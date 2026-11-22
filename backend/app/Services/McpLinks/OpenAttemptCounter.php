<?php

namespace App\Services\McpLinks;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Counter of the opening attempts. The count, the end of the window and the identifier of the
 * window (its generation) live in ONE cache entry, changed only under a cache lock named after
 * the counter. The framework limiter keeps the count and its timer in two entries that expire
 * separately, so a reservation could not tell which window it had been counted in.
 */
class OpenAttemptCounter
{
    public function __construct(private readonly Repository $cache) {}

    /**
     * Counts one attempt and returns the window it was counted in.
     *
     * @return array{generation: string, count: int, expires_at: int}
     *
     * @throws TooManyOpenAttemptsException when the lock is not obtained in time
     */
    public function reserve(string $key, int $decaySeconds): array
    {
        return $this->underLock($key, function () use ($key, $decaySeconds): array {
            $window = $this->currentWindow($key) ?? [
                'generation' => (string) Str::uuid(),
                'count' => 0,
                'expires_at' => now()->getTimestamp() + $decaySeconds,
            ];
            $window['count']++;
            $this->store($key, $window);

            return $window;
        });
    }

    /**
     * Gives one attempt back only to the window it was reserved in: a window that ended
     * meanwhile and was started again by someone else is not the caller's.
     *
     * @throws TooManyOpenAttemptsException when the lock is not obtained in time
     */
    public function release(string $key, string $generation): void
    {
        $this->underLock($key, function () use ($key, $generation): void {
            $window = $this->currentWindow($key);

            if ($window === null || $window['generation'] !== $generation) {
                return;
            }

            $window['count'] = max(0, $window['count'] - 1);
            $this->store($key, $window);
        });
    }

    /**
     * The end of the window is checked here and not left to the store's expiry alone.
     *
     * @return array{generation: string, count: int, expires_at: int}|null
     */
    private function currentWindow(string $key): ?array
    {
        $window = $this->cache->get($key);

        if (! is_array($window) || ! isset($window['generation'], $window['count'], $window['expires_at'])) {
            return null;
        }

        return $window['expires_at'] > now()->getTimestamp() ? $window : null;
    }

    /**
     * @param  array{generation: string, count: int, expires_at: int}  $window
     */
    private function store(string $key, array $window): void
    {
        $this->cache->put($key, $window, $window['expires_at'] - now()->getTimestamp());
    }

    /**
     * A request that cannot get the lock in a short time is refused: the cautious side.
     *
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     *
     * @throws TooManyOpenAttemptsException
     */
    private function underLock(string $key, Closure $change): mixed
    {
        try {
            return Cache::lock('lock:'.$key, (int) config('mcp-link.open_attempts.counter_lock_seconds'))
                ->block((int) config('mcp-link.open_attempts.counter_lock_wait_seconds'), $change);
        } catch (LockTimeoutException) {
            throw new TooManyOpenAttemptsException(1);
        }
    }
}

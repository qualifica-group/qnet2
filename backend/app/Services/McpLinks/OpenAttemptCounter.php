<?php

namespace App\Services\McpLinks;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Counter of the opening attempts, in windows. A window is one cache entry (its identifier,
 * the generation, and its end) created with `add`; its count is a second entry whose key
 * carries the generation, changed only with `increment` and `decrement`. Both are atomic
 * operations of the cache store, so no request ever writes back a count it read earlier:
 * a request that resumes late still adds to the current count, and an attempt can only be
 * given back to the window it was counted in. The framework limiter keeps the count and its
 * timer in two entries that expire separately and falls back to a non-atomic write.
 *
 * The cache lock only serialises the requests on one counter; the count does not depend on it.
 * Every write the store refuses ends as a refused attempt: no password is checked without a
 * counted attempt.
 */
class OpenAttemptCounter
{
    private const INITIAL_COUNT = 0;

    public function __construct(private readonly Repository $cache) {}

    /**
     * Counts one attempt and returns the window it was counted in.
     *
     * @return array{generation: string, count: int, expires_at: int}
     *
     * @throws TooManyOpenAttemptsException when the lock is not obtained in time or the store refuses the write
     */
    public function reserve(string $key, int $decaySeconds): array
    {
        return $this->underLock($key, function () use ($key, $decaySeconds): array {
            $window = $this->openWindow($key, $decaySeconds);
            $countKey = $this->countKey($key, $window['generation']);

            $this->cache->add($countKey, self::INITIAL_COUNT, $this->secondsLeft($window));
            $count = $this->cache->increment($countKey);

            if (! is_int($count)) {
                throw new TooManyOpenAttemptsException($this->secondsLeft($window));
            }

            return [...$window, 'count' => $count];
        });
    }

    /**
     * Gives one attempt back to the window it was reserved in. The key of the count carries
     * the generation, so a window that ended meanwhile and was started again by someone else
     * is never touched.
     *
     * @throws TooManyOpenAttemptsException when the lock is not obtained in time
     */
    public function release(string $key, string $generation): void
    {
        $this->underLock($key, function () use ($key, $generation): void {
            $countKey = $this->countKey($key, $generation);
            $count = $this->cache->decrement($countKey);

            // The window had ended: a store that creates the entry on decrement must not keep it.
            if (is_int($count) && $count < self::INITIAL_COUNT) {
                $this->cache->forget($countKey);
            }
        });
    }

    /**
     * The current window, or a new one. `add` writes only if no window exists: of two requests
     * that start a window together, both count in the one that was written first.
     *
     * @return array{generation: string, expires_at: int}
     *
     * @throws TooManyOpenAttemptsException when the store keeps no window
     */
    private function openWindow(string $key, int $decaySeconds): array
    {
        $window = $this->currentWindow($key);

        if ($window !== null) {
            return $window;
        }

        $this->cache->add($key, [
            'generation' => (string) Str::uuid(),
            'expires_at' => now()->getTimestamp() + $decaySeconds,
        ], $decaySeconds);

        return $this->currentWindow($key) ?? throw new TooManyOpenAttemptsException($decaySeconds);
    }

    /**
     * The end of a window is the expiry of its entry in the store.
     *
     * @return array{generation: string, expires_at: int}|null
     */
    private function currentWindow(string $key): ?array
    {
        $window = $this->cache->get($key);

        return is_array($window) && isset($window['generation'], $window['expires_at']) ? $window : null;
    }

    private function countKey(string $key, string $generation): string
    {
        return "{$key}:count:{$generation}";
    }

    /**
     * @param  array{generation: string, expires_at: int}  $window
     */
    private function secondsLeft(array $window): int
    {
        return max(1, $window['expires_at'] - now()->getTimestamp());
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

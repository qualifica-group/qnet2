<?php

declare(strict_types=1);

namespace App\Support\Cache;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Stale-while-revalidate cache for heavy aggregates (spec 0178, D-3/D-4).
 *
 * Only arrays and scalars are cacheable: a serialized Eloquent Collection
 * comes back as __PHP_Incomplete_Class from the `database`/`file` stores.
 * The last value is retained beyond `stale` so a request that times out on the
 * recompute lock can serve it. A store failure never fails the request: it is
 * logged and the value is computed directly.
 */
final class AggregateCache
{
    private const KEY_PREFIX = 'aggregates:';

    private const LOCK_PREFIX = 'aggregates:lock:';

    private const ACTOR_WRITE_PREFIX = 'aggregates:actor-wrote:';

    private const MS_PER_SECOND = 1000;

    /**
     * @template T of array|int|float|string|bool|null
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    public function remember(string $key, Closure $compute, ?User $actor = null): mixed
    {
        $storeKey = self::KEY_PREFIX.$key;

        // Step 1: read the stored entry; a broken store means "compute directly"
        $entry = $this->guard(fn () => $this->readEntry($storeKey), false);
        if ($entry === false) {
            return $this->checked($compute());
        }

        // Step 2: a missing/expired entry, or one older than a write by this actor, is recomputed inline
        $age = $this->ageSeconds($entry);
        $actorWroteAfter = $entry !== null && $this->actorWroteAfter($actor, $entry);
        if ($entry === null || $age > $this->stale() || $actorWroteAfter) {
            return $this->recomputeUnderLock($storeKey, $compute, $actor);
        }

        // Step 3: serve the stored value, refreshing it after the response once it is no longer fresh
        if ($age > $this->fresh()) {
            defer(fn () => $this->refreshInBackground($storeKey, $compute));
        }

        return $entry['value'];
    }

    /** Remembers that the actor just wrote, so their next read is exact (D-4). */
    public function recordWrite(User $actor): void
    {
        $this->guard(fn () => Cache::put(
            self::ACTOR_WRITE_PREFIX.$actor->getKey(),
            $this->nowMs(),
            $this->retain(),
        ));
    }

    private function recomputeUnderLock(string $storeKey, Closure $compute, ?User $actor): mixed
    {
        $lock = $this->guard(fn () => Cache::lock(self::LOCK_PREFIX.$storeKey, $this->lockSeconds()), null);
        if ($lock === null) {
            return $this->computeAndStore($storeKey, $compute);
        }

        if (! $this->acquire($lock)) {
            return $this->serveLastValueOrCompute($storeKey, $compute, $actor);
        }

        try {
            // Whoever held the lock may have stored the value in the meantime
            $entry = $this->guard(fn () => $this->readEntry($storeKey));
            if ($entry !== null && $this->ageSeconds($entry) <= $this->fresh() && ! $this->actorWroteAfter($actor, $entry)) {
                return $entry['value'];
            }

            return $this->computeAndStore($storeKey, $compute);
        } finally {
            $this->guard(fn () => $lock->release());
        }
    }

    /**
     * Lock wait timed out: another process is still computing. Piling up a duplicate
     * computation only worsens the load, so serve the last retained value; compute
     * only when there is none, or when the actor wrote after it (D-4 exactness).
     */
    private function serveLastValueOrCompute(string $storeKey, Closure $compute, ?User $actor): mixed
    {
        $entry = $this->guard(fn () => $this->readEntry($storeKey));
        if ($entry !== null && ! $this->actorWroteAfter($actor, $entry)) {
            return $entry['value'];
        }

        return $this->computeAndStore($storeKey, $compute);
    }

    private function refreshInBackground(string $storeKey, Closure $compute): void
    {
        $lock = $this->guard(fn () => Cache::lock(self::LOCK_PREFIX.$storeKey, $this->lockSeconds()), null);
        // Non-blocking: only one refresher per key, the others keep serving the old value
        if ($lock === null || ! $this->guard(fn () => $lock->get(), false)) {
            return;
        }

        try {
            $entry = $this->guard(fn () => $this->readEntry($storeKey));
            if ($entry === null || $this->ageSeconds($entry) > $this->fresh()) {
                $this->computeAndStore($storeKey, $compute);
            }
        } finally {
            $this->guard(fn () => $lock->release());
        }
    }

    /** True when the lock is ours; false on timeout or store failure (caller falls back to the last value or computes). */
    private function acquire(Lock $lock): bool
    {
        try {
            $lock->block($this->lockWait());

            return true;
        } catch (LockTimeoutException) {
            return false;
        } catch (Throwable $e) {
            $this->logStoreFailure($e);

            return false;
        }
    }

    private function computeAndStore(string $storeKey, Closure $compute): mixed
    {
        $value = $this->checked($compute());
        $this->guard(fn () => Cache::put(
            $storeKey,
            ['value' => $value, 'computed_at' => $this->nowMs()],
            $this->retain(),
        ));

        return $value;
    }

    /** @return array{value: mixed, computed_at: int}|null */
    private function readEntry(string $storeKey): ?array
    {
        $entry = Cache::get($storeKey);

        return is_array($entry) && array_key_exists('value', $entry) && isset($entry['computed_at']) ? $entry : null;
    }

    private function actorWroteAfter(?User $actor, array $entry): bool
    {
        if ($actor === null) {
            return false;
        }

        $wroteAt = $this->guard(fn () => Cache::get(self::ACTOR_WRITE_PREFIX.$actor->getKey()));

        return is_numeric($wroteAt) && (int) $wroteAt > $entry['computed_at'];
    }

    private function ageSeconds(?array $entry): float
    {
        return $entry === null ? INF : ($this->nowMs() - $entry['computed_at']) / self::MS_PER_SECOND;
    }

    /** Programming error, never swallowed: objects do not survive every cache store. */
    private function checked(mixed $value): mixed
    {
        if (is_object($value)) {
            throw new InvalidArgumentException('AggregateCache accepts only arrays and scalars, got '.$value::class.'.');
        }

        if (is_array($value)) {
            array_walk_recursive($value, function (mixed $item): void {
                $this->checked($item);
            });
        }

        return $value;
    }

    /** Runs a store operation; on failure logs and returns $default. */
    private function guard(Closure $operation, mixed $default = null): mixed
    {
        try {
            return $operation();
        } catch (Throwable $e) {
            $this->logStoreFailure($e);

            return $default;
        }
    }

    private function logStoreFailure(Throwable $e): void
    {
        Log::warning('AggregateCache store failure, computing directly.', ['exception' => $e::class, 'message' => $e->getMessage()]);
    }

    private function nowMs(): int
    {
        return Carbon::now()->getTimestampMs();
    }

    private function fresh(): int
    {
        return (int) config('aggregate-cache.fresh_seconds');
    }

    private function stale(): int
    {
        return (int) config('aggregate-cache.stale_seconds');
    }

    private function retain(): int
    {
        return (int) config('aggregate-cache.retain_seconds');
    }

    private function lockSeconds(): int
    {
        return (int) config('aggregate-cache.lock_seconds');
    }

    private function lockWait(): int
    {
        return (int) config('aggregate-cache.lock_wait_seconds');
    }
}

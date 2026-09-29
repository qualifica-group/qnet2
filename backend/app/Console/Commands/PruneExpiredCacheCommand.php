<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0178, D-6: the `database` cache store never deletes expired rows that
 * are not read again. Deletes them in bounded chunks to avoid long table locks.
 */
final class PruneExpiredCacheCommand extends Command
{
    protected $signature = 'cache:prune-expired';

    protected $description = 'Delete expired rows from the database cache table (no-op for other stores)';

    public function handle(): int
    {
        // Step 1: only the database store leaves expired rows behind
        if (config('cache.default') !== 'database') {
            $this->info('Default cache store is not "database": nothing to prune.');

            return self::SUCCESS;
        }

        // Step 2: delete expired rows chunk by chunk
        $deleted = $this->prune();
        $this->info("Pruned {$deleted} expired cache rows.");

        return self::SUCCESS;
    }

    private function prune(): int
    {
        $table = DB::connection(config('cache.stores.database.connection'))
            ->table(config('cache.stores.database.table'));
        $chunk = max(1, (int) config('aggregate-cache.prune_chunk'));
        $now = Carbon::now()->getTimestamp();
        $total = 0;

        do {
            $keys = (clone $table)->where('expiration', '<=', $now)->limit($chunk)->pluck('key');
            $deleted = $keys->isEmpty() ? 0 : (clone $table)->whereIn('key', $keys)->delete();
            $total += $deleted;
        } while ($deleted === $chunk);

        return $total;
    }
}

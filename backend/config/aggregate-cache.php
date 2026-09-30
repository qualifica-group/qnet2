<?php

/*
|--------------------------------------------------------------------------
| Aggregate cache (spec 0178)
|--------------------------------------------------------------------------
|
| Tuning of App\Support\Cache\AggregateCache: heavy read-only aggregates
| (category tabs, report dashboard, stats, filter values) computed once and
| reused for a few seconds. The backing store is whatever CACHE_STORE says.
|
*/

return [

    // A value younger than this is served as is.
    'fresh_seconds' => (int) env('AGGREGATE_CACHE_FRESH_SECONDS', 10),

    // Between fresh and stale the old value is served while ONE process
    // recomputes it after the response; beyond stale it is recomputed inline.
    'stale_seconds' => (int) env('AGGREGATE_CACHE_STALE_SECONDS', 300),

    // How long the last computed value stays in the store. Age is read from
    // computed_at, so this does not change fresh/stale: it only keeps a value
    // available for requests that time out waiting for the recompute lock.
    'retain_seconds' => (int) env('AGGREGATE_CACHE_RETAIN_SECONDS', 3600),

    // Maximum lifetime of the per-key recompute lock (crash safety); must
    // outlast the longest computation under load.
    'lock_seconds' => (int) env('AGGREGATE_CACHE_LOCK_SECONDS', 120),

    // How long a request waits for the lock; on timeout it serves the last
    // value, and computes on its own only if none exists.
    'lock_wait_seconds' => (int) env('AGGREGATE_CACHE_LOCK_WAIT_SECONDS', 5),

    // Rows deleted per statement by `cache:prune-expired`.
    'prune_chunk' => (int) env('AGGREGATE_CACHE_PRUNE_CHUNK', 1000),

];

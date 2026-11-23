<?php

return [

    // Master switch. Disabled (default): the provider loads only its
    // migrations, nothing else is reachable or visible.
    'enabled' => (bool) env('FORMALAB_ENABLED', false),

    'url' => env('FORMALAB_URL'),
    'user_email' => env('FORMALAB_USER_EMAIL'),
    'user_password' => env('FORMALAB_USER_PASSWORD'),

    'timeout_seconds' => (int) env('FORMALAB_TIMEOUT_SECONDS', 30),
    'health_timeout_seconds' => (int) env('FORMALAB_HEALTH_TIMEOUT_SECONDS', 5),
    'token_ttl_minutes' => (int) env('FORMALAB_TOKEN_TTL_MINUTES', 1440),

    'sync_interval_minutes' => (int) env('FORMALAB_SYNC_INTERVAL_MINUTES', 60),

    // The catalogue sync runs on its own queue, served by one worker process:
    // a long sync never holds up the other queues and two syncs never overlap.
    'queue' => env('FORMALAB_QUEUE', 'formalab'),

    // The worker kills a sync running longer than this (a full sync takes
    // 5-10 minutes). Must stay below queue.connections.database.retry_after.
    'sync_timeout_seconds' => (int) env('FORMALAB_SYNC_TIMEOUT_SECONDS', 1200),
    'log_retention_days' => (int) env('FORMALAB_LOG_RETENTION_DAYS', 90),

    // Shared secret Formalab sends in X-Formalab-Token. Empty: no inbound route.
    'inbound_token' => env('FORMALAB_INBOUND_TOKEN'),

    'page_size' => 100,
    'max_pages' => 200,

];

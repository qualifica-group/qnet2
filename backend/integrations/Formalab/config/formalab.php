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

    // "Prospetto contabile discenti" (spec 0427): the legacy figures and the
    // products standing for the legacy service 1326 and expense reports 8/9.
    'learner_statement' => [
        'cost_per_hour' => 1.0,
        'revenue_per_hour' => 0.85,
        'cache_minutes' => (int) env('FORMALAB_LEARNER_STATEMENT_CACHE_MINUTES', 360),
        'advance_attribute' => 'gol_importo_anticipo',
        'balance_attribute' => 'gol_importo_saldo',
        'learner_revenue_product' => ['old_source' => 'services', 'old_id' => 1326],
        'learner_cost_product' => ['old_source' => 'expense_reports', 'old_id' => 8],
        'partner_commission_product' => ['old_source' => 'expense_reports', 'old_id' => 9],
    ],

];

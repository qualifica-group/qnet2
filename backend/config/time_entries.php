<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default daily target (minutes)
    |--------------------------------------------------------------------------
    |
    | Fallback for `target_minutes` (D-7) when the user has no employment
    | profile, or `standard_daily_minutes` is null: 8h.
    |
    */

    'default_daily_minutes' => (int) env('TIME_ENTRIES_DEFAULT_DAILY_MINUTES', 480),

    /*
    |--------------------------------------------------------------------------
    | Max period range (days)
    |--------------------------------------------------------------------------
    |
    | `date_from`/`date_to` may not span more than this many days (data_contract
    | F, AC-015).
    |
    */

    'max_range_days' => (int) env('TIME_ENTRIES_MAX_RANGE_DAYS', 366),

    /*
    |--------------------------------------------------------------------------
    | Pagination defaults
    |--------------------------------------------------------------------------
    |
    | GET /api/time-entries `per_page` (data_contract).
    |
    */

    'default_per_page' => (int) env('TIME_ENTRIES_DEFAULT_PER_PAGE', 15),

    'max_per_page' => (int) env('TIME_ENTRIES_MAX_PER_PAGE', 100),

];

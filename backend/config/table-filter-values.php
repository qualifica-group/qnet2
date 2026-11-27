<?php

/*
|--------------------------------------------------------------------------
| Value lists of the "Excel" column filters (spec 0005)
|--------------------------------------------------------------------------
*/

return [

    // Most distinct values a column's checklist lists. A text/number/date
    // column with more values offers only its condition filter (Contiene,
    // date, number): user decision 2026-11-27.
    'limit' => (int) env('TABLE_FILTER_VALUES_LIMIT', 2000),

    // How long the per-user list of those columns is served as is; until
    // `stale_seconds` it is still served while one process recomputes it
    // after the response, beyond that it is recomputed inline.
    'large_columns_fresh_seconds' => (int) env('TABLE_FILTER_LARGE_COLUMNS_FRESH_SECONDS', 3600),

    'large_columns_stale_seconds' => (int) env('TABLE_FILTER_LARGE_COLUMNS_STALE_SECONDS', 86400),

];

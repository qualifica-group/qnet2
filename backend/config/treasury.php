<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Treasury (spec 0355)
    |--------------------------------------------------------------------------
    |
    | default_opening_date: opening date of a financial account that has no own
    | opening_balance_date; facts before it are excluded from balances.
    | statement_max_rows: cap on the rows of one account statement.
    |
    */

    'default_opening_date' => env('TREASURY_DEFAULT_OPENING_DATE', '2026-01-01'),

    'statement_max_rows' => 5000,

];

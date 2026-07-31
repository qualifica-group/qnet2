<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Expiring-within window (days)
    |--------------------------------------------------------------------------
    |
    | A contract's `alert` (spec 0072 BR-6) is `expiring` when its
    | `expiry_date` falls within today + this many days (D-4). Calculated at
    | read time, never persisted.
    |
    */

    'expiring_within_days' => (int) env('CONTRACTS_EXPIRING_WITHIN_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Renewal-due window (days)
    |--------------------------------------------------------------------------
    |
    | A contract's `alert` is `renewal_due` when its `renewal_date` falls
    | within today + this many days (D-4), UNLESS `expiring` already applies —
    | the expiry alert always takes precedence (BR-6).
    |
    */

    'renewal_within_days' => (int) env('CONTRACTS_RENEWAL_WITHIN_DAYS', 30),

];

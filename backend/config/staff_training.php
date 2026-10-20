<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Staff training (spec 0284)
    |--------------------------------------------------------------------------
    |
    | `expiring_days`: a passed training whose expiry falls within this many
    | days from today is reported as "expiring" in the compliance view.
    |
    */
    'expiring_days' => (int) env('STAFF_TRAINING_EXPIRING_DAYS', 60),

];

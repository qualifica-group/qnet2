<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Settlement minimum amount (spec 0213, D-6)
    |--------------------------------------------------------------------------
    |
    | Default threshold below which a settlement run skips an (agent, company)
    | pair and carries the movements forward. A string: the amounts are bcmath.
    | The run request may override it per launch.
    */
    'settlement_min_amount' => env('COMMISSION_SETTLEMENT_MIN_AMOUNT', '0.00'),

];

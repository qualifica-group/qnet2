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

    /*
    |--------------------------------------------------------------------------
    | Agent document tolerance (spec 0267, D-3)
    |--------------------------------------------------------------------------
    |
    | Largest absolute difference accepted between an amount declared on the
    | agent document and the same amount of the settlement. A string: bcmath.
    */
    'document_tolerance' => env('COMMISSION_DOCUMENT_TOLERANCE', '1.00'),

];

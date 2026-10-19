<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Monthly salary hours
    |--------------------------------------------------------------------------
    |
    | The conventional CCNL divisor turning a monthly salary into an hourly
    | rate (spec 0277 D-1): monthly / 173, yearly / (173 x 12).
    |
    */

    'monthly_salary_hours' => (int) env('EMPLOYMENT_MONTHLY_SALARY_HOURS', 173),

];

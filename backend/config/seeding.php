<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Development seed credentials
    |--------------------------------------------------------------------------
    |
    | Shared plain-text password assigned to every account created by the
    | seeders: the demo user and fixtures (DemoUserSeeder, DemoUsersSeeder),
    | the named testers (TestUsersSeeder) and the client's operators
    | (QualificaOperatorSeeder). It only exists to make seeded accounts
    | loginable; it is never a production secret. Override it via SEED_PASSWORD.
    |
    */

    'password' => env('SEED_PASSWORD', 'Qualifica2026!'),

];
